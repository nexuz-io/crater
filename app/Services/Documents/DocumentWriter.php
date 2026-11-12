<?php

namespace Crater\Services\Documents;

use Crater\Http\Requests\InvoicesRequest;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\ExchangeRateLog;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\ConnectionInterface;
use Vinkla\Hashids\Facades\Hashids;

/**
 * DocumentWriter (plan 09 §F2, PR-03) — HR7 atomicidad.
 *
 * Encapsula la creación y actualización de una Invoice en un único
 * `DB::transaction`. Si cualquier paso falla (items, taxes, custom
 * fields, ExchangeRateLog, …) el sistema hace rollback completo, sin
 * dejar invoices huérfanas con totales viejos o items a medias.
 *
 * Estrategia A2 (decisión PR-03): la transacción cubre TODO el flujo,
 * incluyendo `Invoice::create($payload)` o `$invoice->update($payload)`,
 * el `SerialNumberFormatter` y la asignación de `unique_hash`. Solo
 * `getInvoicePayload()` se calcula fuera (en la request) porque no
 * toca la base de datos.
 *
 * El writer mantiene compat con los métodos `Invoice::createItems()` /
 * `Invoice::createTaxes()` (aún públicos para F4 / RecurringInvoice),
 * pero su implementación interna los replica para no invocar métodos
 * estáticos dentro de la transacción (lo que dificulta el tracing del
 * rollback y duplica la responsabilidad).
 */
final class DocumentWriter
{
    public function __construct(
        private ConnectionInterface $db,
        private DocumentTotalsCalculator $totals,
    ) {
    }

    public function createInvoice(InvoicesRequest $request): Invoice
    {
        return $this->db->transaction(function () use ($request) {
            $data = $request->getInvoicePayload();

            if ($request->has('invoiceSend')) {
                $data['status'] = Invoice::STATUS_SENT;
            }

            $invoice = Invoice::create($data);

            // SerialNumberFormatter chain (D5/D7).
            $serial = (new SerialNumberFormatter())
                ->setModel($invoice)
                ->setCompany($invoice->company_id)
                ->setCustomer($invoice->customer_id)
                ->setNextNumbers();
            $invoice->sequence_number = $serial->nextSequenceNumber;
            $invoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
            $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
            $invoice->save();

            // Items + taxes + taxes por item (recursivo) en un solo paso.
            $itemsPayload = $request->has('items') ? (array) $request->items : [];
            $taxesPayload = ($request->has('taxes') && ! empty($request->taxes)) ? (array) $request->taxes : [];

            $this->syncLines($invoice, $itemsPayload, $taxesPayload);

            // ExchangeRateLog si currency difiere de la empresa.
            $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));
            if ((string) $data['currency_id'] !== (string) $companyCurrency) {
                ExchangeRateLog::addExchangeRateLog($invoice);
            }

            // Custom fields del invoice.
            if ($request->customFields) {
                $invoice->addCustomFields($request->customFields);
            }

            return Invoice::with([
                'items',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
            ])->find($invoice->id);
        });
    }

    public function updateInvoice(Invoice $invoice, InvoicesRequest $request): Invoice
    {
        return $this->db->transaction(function () use ($invoice, $request) {
            $serial = (new SerialNumberFormatter())
                ->setModel($invoice)
                ->setCompany($invoice->company_id)
                ->setCustomer($request->customer_id)
                ->setModelObject($invoice->id)
                ->setNextNumbers();

            $data = $request->getInvoicePayload();
            $oldTotal = $invoice->total;

            $total_paid_amount = $invoice->total - $invoice->due_amount;

            if ($total_paid_amount > 0 && $invoice->customer_id !== $request->customer_id) {
                return 'customer_cannot_be_changed_after_payment_is_added';
            }

            if ($request->total < $total_paid_amount) {
                return 'total_invoice_amount_must_be_more_than_paid_amount';
            }

            if ($oldTotal != $request->total) {
                $oldTotal = (int) round($request->total) - (int) $oldTotal;
            } else {
                $oldTotal = 0;
            }

            $data['due_amount'] = ($invoice->due_amount + $oldTotal);
            $data['base_due_amount'] = $data['due_amount'] * $data['exchange_rate'];
            $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

            $invoice->changeInvoiceStatus($data['due_amount']);

            $invoice->update($data);

            $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));
            if ((string) $data['currency_id'] !== (string) $companyCurrency) {
                ExchangeRateLog::addExchangeRateLog($invoice);
            }

            // Custom fields de líneas (item.fields) primero.
            $invoice->items->map(function ($item) {
                $fields = $item->fields()->get();
                $fields->map(function ($field) {
                    $field->delete();
                });
            });

            $itemsPayload = $request->has('items') ? (array) $request->items : [];
            $taxesPayload = ($request->has('taxes') && ! empty($request->taxes)) ? (array) $request->taxes : [];

            $this->syncLines($invoice, $itemsPayload, $taxesPayload, replace: true);

            // Custom fields del invoice.
            if ($request->customFields) {
                $invoice->updateCustomFields($request->customFields);
            }

            return Invoice::with([
                'items',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
            ])->find($invoice->id);
        });
    }

    /**
     * Sustituye líneas + taxes en transacción (HR7).
     *
     * Para invoices nuevas los items y taxes ya están vacíos, por lo
     * que `replace=false` simplemente crea. Para updates, `replace=true`
     * borra los anteriores tras limpiar `item.fields`.
     */
    private function syncLines(Invoice $invoice, array $itemsPayload, array $taxesPayload, bool $replace = false): void
    {
        $exchangeRate = $invoice->exchange_rate;

        if ($replace) {
            $invoice->items()->delete();
            $invoice->taxes()->delete();
        }

        foreach ($itemsPayload as $itemData) {
            $itemData['company_id'] = $invoice->company_id;
            $itemData['exchange_rate'] = $exchangeRate;
            $itemData['base_price'] = $itemData['price'] * $exchangeRate;
            $itemData['base_discount_val'] = $itemData['discount_val'] * $exchangeRate;
            $itemData['base_tax'] = $itemData['tax'] * $exchangeRate;
            $itemData['base_total'] = $itemData['total'] * $exchangeRate;

            if (array_key_exists('recurring_invoice_id', $itemData)) {
                unset($itemData['recurring_invoice_id']);
            }

            $item = $invoice->items()->create($itemData);

            // Taxes por item (cuando tax_per_item === 'YES').
            if (array_key_exists('taxes', $itemData) && $itemData['taxes']) {
                foreach ($itemData['taxes'] as $tax) {
                    $tax['company_id'] = $invoice->company_id;
                    $tax['exchange_rate'] = $invoice->exchange_rate;
                    $tax['base_amount'] = $tax['amount'] * $exchangeRate;
                    $tax['currency_id'] = $invoice->currency_id;

                    if (array_key_exists('recurring_invoice_id', $tax)) {
                        unset($tax['recurring_invoice_id']);
                    }

                    if (gettype($tax['amount']) !== 'NULL') {
                        $item->taxes()->create($tax);
                    }
                }
            }

            // Custom fields del item (sync); el writer no obliga a llegar
            // vacíos.
            if (array_key_exists('custom_fields', $itemData) && $itemData['custom_fields']) {
                $item->addCustomFields($itemData['custom_fields']);
            }
        }

        foreach ($taxesPayload as $tax) {
            $tax['company_id'] = $invoice->company_id;
            $tax['exchange_rate'] = $invoice->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $exchangeRate;
            $tax['currency_id'] = $invoice->currency_id;

            if (array_key_exists('recurring_invoice_id', $tax)) {
                unset($tax['recurring_invoice_id']);
            }

            if (gettype($tax['amount']) !== 'NULL') {
                $invoice->taxes()->create($tax);
            }
        }
    }
}

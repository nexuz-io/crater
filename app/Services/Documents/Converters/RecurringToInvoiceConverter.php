<?php

namespace Crater\Services\Documents\Converters;

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;
use Crater\Services\Documents\DocumentConverterInterface;
use Crater\Services\SerialNumberFormatter;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Plan 09 §F4 — RecurringToInvoiceConverter.
 *
 * Porta literal del cuerpo de `RecurringInvoice::createInvoice`
 * (líneas 320-383 del modelo). Reusa los métodos públicos estáticos
 * `Invoice::createItems()` y `Invoice::createTaxes()` que ya están
 * probados y son la única entrada histórica para que las líneas y
 * taxes derivadas se creen con los campos `base_*` correctos.
 *
 * El método está preservando `send_automatically` (al igual que el
 * original) — el envío de correo por `Invoice::send()` ocurre dentro
 * del converter cuando la recurring lo solicita. Es deliberado: la
 * extracción a un `InvoiceMailer` separado queda fuera del scope
 * de F4 y entra con F9-F10 del plan 09.
 *
 * NO incluye los guards de `generateInvoice`:
 *   - `Carbon::now()->lessThan($this->starts_at)` — handled por generateInvoice
 *   - `limit_by = DATE | COUNT` — handled por generateInvoice
 * Esos guards preceden al converter en `generateInvoice`; el converter
 * confía en que solo se le invoca con un RecurringInvoice válido.
 */
final class RecurringToInvoiceConverter implements DocumentConverterInterface
{
    public function sourceType(): string
    {
        return 'recurring-invoice';
    }

    public function targetType(): string
    {
        return 'invoice';
    }

    public function convert(object $origin): object
    {
        if (! $origin instanceof RecurringInvoice) {
            throw new \InvalidArgumentException(
                'RecurringToInvoiceConverter expects a RecurringInvoice, got '.get_class($origin)
            );
        }

        $recurring = $origin;
        $recurring->load('items.taxes');

        $serial = (new SerialNumberFormatter())
            ->setModel(new Invoice())
            ->setCompany($recurring->company_id)
            ->setCustomer($recurring->customer_id)
            ->setNextNumbers();

        $days = CompanySetting::getSetting('invoice_due_date_days', $recurring->company_id);
        if (! $days || $days == 'null') {
            $days = 7;
        }

        $payload = [
            'creator_id' => $recurring->creator_id,
            'invoice_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays((int) $days)->format('Y-m-d'),
            'status' => Invoice::STATUS_DRAFT,
            'company_id' => $recurring->company_id,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $recurring->sub_total,
            'tax_per_item' => $recurring->tax_per_item,
            'discount_per_item' => $recurring->discount_per_item,
            'tax' => $recurring->tax,
            'total' => $recurring->total,
            'customer_id' => $recurring->customer_id,
            'currency_id' => Customer::find($recurring->customer_id)->currency_id,
            'template_name' => $recurring->template_name,
            'due_amount' => $recurring->total,
            'recurring_invoice_id' => $recurring->id,
            'discount_val' => $recurring->discount_val,
            'discount' => $recurring->discount,
            'discount_type' => $recurring->discount_type,
            'notes' => $recurring->notes,
            'exchange_rate' => $recurring->exchange_rate,
            'sales_tax_type' => $recurring->sales_tax_type,
            'sales_tax_address_type' => $recurring->sales_tax_address_type,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'base_due_amount' => $recurring->exchange_rate * $recurring->due_amount,
            'base_discount_val' => $recurring->exchange_rate * $recurring->discount_val,
            'base_sub_total' => $recurring->exchange_rate * $recurring->sub_total,
            'base_tax' => $recurring->exchange_rate * $recurring->tax,
            'base_total' => $recurring->exchange_rate * $recurring->total,
        ];

        $invoice = Invoice::create($payload);
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        // Reusa `Invoice::createItems` y `Invoice::createTaxes` públicos.
        // Estos métodos siguen vivos por compatibilidad con este converter;
        // `DocumentWriter::syncLines` los replica para casos atómicos.
        Invoice::createItems($invoice, $recurring->items->toArray());

        if ($recurring->taxes()->exists()) {
            Invoice::createTaxes($invoice, $recurring->taxes->toArray());
        }

        if ($recurring->fields()->exists()) {
            $customField = [];
            foreach ($recurring->fields as $data) {
                $customField[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }
            $invoice->addCustomFields($customField);
        }

        if ($recurring->send_automatically == true) {
            $data = [
                'body' => CompanySetting::getSetting('invoice_mail_body', $recurring->company_id),
                'from' => config('mail.from.address'),
                'to' => $recurring->customer->email,
                'subject' => 'New Invoice',
                'invoice' => $invoice->toArray(),
                'customer' => $invoice->customer->toArray(),
                'company' => Company::find($invoice->company_id),
            ];
            $invoice->send($data);
        }

        return $invoice;
    }
}

<?php

namespace Crater\Services\Documents\Converters;

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Services\Documents\DocumentConverterInterface;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Plan 09 §F3 + §F8 — EstimateToInvoiceConverter con idempotencia.
 *
 * Porta literal del cuerpo de `ConvertEstimateController::__invoke`
 * (líneas 25-133 del archivo original). En F8 añadimos el wrapping en
 * `DB::transaction` con `lockForUpdate` sobre el Estimate y el chequeo
 * de idempotencia: si ya existe una `Invoice` con
 * `from_estimate_id = $estimate->id`, la devolvemos tal cual sin
 * volver a crearla.
 *
 * Esto refuerza G3 del plan 06 §paso-8: dos clicks seguidos sobre el
 * mismo Estimate (e.g. un user doble clickeando el botón "Convert") NO
 * producen dos Invoices, gracias al lock y al chequeo dentro de la
 * transacción.
 */
final class EstimateToInvoiceConverter implements DocumentConverterInterface
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function sourceType(): string
    {
        return 'estimate';
    }

    public function targetType(): string
    {
        return 'invoice';
    }

    public function convert(object $origin): object
    {
        if (! $origin instanceof Estimate) {
            throw new \InvalidArgumentException(
                'EstimateToInvoiceConverter expects an Estimate, got '.get_class($origin)
            );
        }

        return $this->db->transaction(function () use ($origin) {
            // (F8) Bloquea el Estimate para serializar clics concurrentes
            // en la misma estimación. Combinado con el chequeo
            // `from_estimate_id`, garantiza idempotencia aún bajo
            // doble click: el segundo proceso verá la Invoice ya
            // creada y la devolverá.
            $estimate = Estimate::where('id', $origin->id)->lockForUpdate()->first();
            if (! $estimate) {
                throw new \RuntimeException('Estimate vanished during conversion');
            }

            $existing = Invoice::where('from_estimate_id', $estimate->id)->first();
            if ($existing) {
                return $existing;
            }

            $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

            $invoice_date = Carbon::now();
            $due_date = null;

            $dueDateEnabled = CompanySetting::getSetting(
                'invoice_set_due_date_automatically',
                $estimate->company_id
            );

            if ($dueDateEnabled === 'YES') {
                $dueDateDays = CompanySetting::getSetting(
                    'invoice_due_date_days',
                    $estimate->company_id
                );
                $due_date = Carbon::now()->addDays((int) $dueDateDays)->format('Y-m-d');
            }

            $serial = (new SerialNumberFormatter())
                ->setModel(new Invoice())
                ->setCompany($estimate->company_id)
                ->setCustomer($estimate->customer_id)
                ->setNextNumbers();

            $templateName = $estimate->getInvoiceTemplateName();
            $exchange_rate = $estimate->exchange_rate;

            // 28 campos + `from_estimate_id` (introducido por plan 09 §F5).
            $invoice = Invoice::create([
                'creator_id' => Auth::id(),
                'invoice_date' => $invoice_date->format('Y-m-d'),
                'due_date' => $due_date,
                'invoice_number' => $serial->getNextNumber(),
                'sequence_number' => $serial->nextSequenceNumber,
                'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
                'reference_number' => $serial->getNextNumber(),
                'customer_id' => $estimate->customer_id,
                'company_id' => $estimate->company_id,
                'template_name' => $templateName,
                'status' => Invoice::STATUS_DRAFT,
                'paid_status' => Invoice::STATUS_UNPAID,
                'sub_total' => $estimate->sub_total,
                'discount' => $estimate->discount,
                'discount_type' => $estimate->discount_type,
                'discount_val' => $estimate->discount_val,
                'total' => $estimate->total,
                'due_amount' => $estimate->total,
                'tax_per_item' => $estimate->tax_per_item,
                'discount_per_item' => $estimate->discount_per_item,
                'tax' => $estimate->tax,
                'notes' => $estimate->notes,
                'exchange_rate' => $exchange_rate,
                'base_discount_val' => $estimate->discount_val * $exchange_rate,
                'base_sub_total' => $estimate->sub_total * $exchange_rate,
                'base_total' => $estimate->total * $exchange_rate,
                'base_tax' => $estimate->tax * $exchange_rate,
                'currency_id' => $estimate->currency_id,
                'sales_tax_type' => $estimate->sales_tax_type,
                'sales_tax_address_type' => $estimate->sales_tax_address_type,
                'from_estimate_id' => $estimate->id,
            ]);

            $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
            $invoice->save();

            foreach ($estimate->items->toArray() as $item) {
                $item['company_id'] = $estimate->company_id;
                $item['exchange_rate'] = $exchange_rate;
                $item['base_price'] = $item['price'] * $exchange_rate;
                $item['base_discount_val'] = $item['discount_val'] * $exchange_rate;
                $item['base_tax'] = $item['tax'] * $exchange_rate;
                $item['base_total'] = $item['total'] * $exchange_rate;

                $created = $invoice->items()->create($item);

                if (array_key_exists('taxes', $item) && $item['taxes']) {
                    foreach ($item['taxes'] as $tax) {
                        $tax['company_id'] = $estimate->company_id;
                        if (! empty($tax['amount'])) {
                            $created->taxes()->create($tax);
                        }
                    }
                }
            }

            if ($estimate->taxes) {
                foreach ($estimate->taxes->toArray() as $tax) {
                    $tax['company_id'] = $estimate->company_id;
                    $tax['exchange_rate'] = $exchange_rate;
                    $tax['base_amount'] = (int) $tax['amount'] * $exchange_rate;
                    $tax['currency_id'] = $estimate->currency_id;
                    unset($tax['estimate_id']);
                    $invoice->taxes()->create($tax);
                }
            }

            $estimate->checkForEstimateConvertAction();

            return $invoice->fresh();
        });
    }
}

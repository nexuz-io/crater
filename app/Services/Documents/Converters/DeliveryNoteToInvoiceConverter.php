<?php

namespace Crater\Services\Documents\Converters;

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\DeliveryNote;
use Crater\Models\Invoice;
use Crater\Services\Documents\DocumentConverterInterface;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Plan 09 §F11 + plan 05 — DeliveryNoteToInvoiceConverter.
 *
 * Convierte un `DeliveryNote` entregado (status = DELIVERED) en una
 * `Invoice` borrador. Usa la columna `invoices.delivery_note_id`
 * (UNIQUE nullable) como fuente única de la relación.
 *
 * Idempotencia (plan 05 §3): si la Invoice ya existe para este
 * DeliveryNote, la devolvemos. La unicidad de la FK + `lockForUpdate`
 * cubren doble click.
 *
 * El port del literal del cuerpo de la conversión sale del propio
 * `DeliveryNote::billToInvoice()` que delega aquí. El converter
 * mantiene la firma del plan (sourceType => targetType).
 */
final class DeliveryNoteToInvoiceConverter implements DocumentConverterInterface
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function sourceType(): string
    {
        return 'delivery-note';
    }

    public function targetType(): string
    {
        return 'invoice';
    }

    public function convert(object $origin): object
    {
        if (! $origin instanceof DeliveryNote) {
            throw new \InvalidArgumentException(
                'DeliveryNoteToInvoiceConverter expects a DeliveryNote, got '.get_class($origin)
            );
        }

        return $this->db->transaction(function () use ($origin) {
            $deliveryNote = DeliveryNote::where('id', $origin->id)
                ->lockForUpdate()
                ->first();
            if (! $deliveryNote) {
                throw new \RuntimeException('DeliveryNote vanished during conversion');
            }

            if ($deliveryNote->status !== DeliveryNote::STATUS_DELIVERED) {
                throw new \RuntimeException(
                    'Only DELIVERED DeliveryNotes can be converted to Invoice.'
                );
            }

            $existing = Invoice::where('delivery_note_id', $deliveryNote->id)->first();
            if ($existing) {
                return $existing;
            }

            $deliveryNote->load('items', 'taxes', 'customer');

            $serial = (new SerialNumberFormatter())
                ->setModel(new Invoice())
                ->setCompany($deliveryNote->company_id)
                ->setCustomer($deliveryNote->customer_id)
                ->setNextNumbers();

            $days = CompanySetting::getSetting('invoice_due_date_days', $deliveryNote->company_id);
            if (! $days || $days == 'null') {
                $days = 7;
            }

            $invoice = Invoice::create([
                'creator_id' => Auth::id(),
                'invoice_date' => Carbon::today()->format('Y-m-d'),
                'due_date' => Carbon::today()->addDays((int) $days)->format('Y-m-d'),
                'invoice_number' => $serial->getNextNumber(),
                'sequence_number' => $serial->nextSequenceNumber,
                'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
                'reference_number' => $serial->getNextNumber(),
                'customer_id' => $deliveryNote->customer_id,
                'company_id' => $deliveryNote->company_id,
                'currency_id' => $deliveryNote->currency_id,
                'template_name' => $serial->getNextNumber() ? 'invoice1' : null,
                'status' => Invoice::STATUS_DRAFT,
                'paid_status' => Invoice::STATUS_UNPAID,
                'sub_total' => $deliveryNote->sub_total,
                'tax_per_item' => $deliveryNote->tax_per_item ?? 'NO',
                'discount_per_item' => $deliveryNote->discount_per_item ?? 'NO',
                'tax' => $deliveryNote->tax,
                'total' => $deliveryNote->total,
                'due_amount' => $deliveryNote->total,
                'discount_val' => $deliveryNote->discount_val ?? 0,
                'discount' => $deliveryNote->discount ?? 0,
                'discount_type' => $deliveryNote->discount_type ?? 'percentage',
                'notes' => $deliveryNote->notes,
                'exchange_rate' => $deliveryNote->exchange_rate ?? 1,
                'base_total' => (int) $deliveryNote->total * (float) ($deliveryNote->exchange_rate ?? 1),
                'base_tax' => (int) $deliveryNote->tax * (float) ($deliveryNote->exchange_rate ?? 1),
                'base_sub_total' => (int) $deliveryNote->sub_total * (float) ($deliveryNote->exchange_rate ?? 1),
                'base_discount_val' => (int) ($deliveryNote->discount_val ?? 0) * (float) ($deliveryNote->exchange_rate ?? 1),
                'sales_tax_type' => $deliveryNote->sales_tax_type,
                'sales_tax_address_type' => $deliveryNote->sales_tax_address_type,
                'delivery_note_id' => $deliveryNote->id,
                'from_estimate_id' => null,
            ]);

            $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
            $invoice->save();

            // Items del delivery_note -> invoice_items. Conservamos
            // snapshot comercial (price, discount_*) que se captura
            // en el albarán.
            foreach ($deliveryNote->items as $line) {
                $invoice->items()->create([
                    'name' => $line->name,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'price' => $line->price ?? 0,
                    'discount_type' => $line->discount_type ?? 'percentage',
                    'discount_val' => $line->discount_val ?? 0,
                    'discount' => $line->discount ?? 0,
                    'tax' => $line->tax ?? 0,
                    'total' => $line->total ?? 0,
                    'exchange_rate' => $line->exchange_rate ?? $deliveryNote->exchange_rate ?? 1,
                    'company_id' => $invoice->company_id,
                    'base_price' => (int) ($line->price ?? 0) * (float) ($line->exchange_rate ?? 1),
                    'base_discount_val' => (int) ($line->discount_val ?? 0) * (float) ($line->exchange_rate ?? 1),
                    'base_tax' => (int) ($line->tax ?? 0) * (float) ($line->exchange_rate ?? 1),
                    'base_total' => (int) ($line->total ?? 0) * (float) ($line->exchange_rate ?? 1),
                ]);
            }

            // Taxes de cabecera del delivery_note -> invoice taxes.
            foreach ($deliveryNote->taxes()->get() as $tax) {
                $payload = $tax->toArray();
                unset($payload['id']);
                $payload['invoice_id'] = $invoice->id;
                $payload['delivery_note_id'] = null; // desvincula
                $invoice->taxes()->create($payload);
            }

            return $invoice->fresh();
        });
    }
}

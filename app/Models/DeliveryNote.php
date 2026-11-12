<?php

namespace Crater\Models;

use Carbon\Carbon;
use Crater\Contracts\Documents\Billable;
use Crater\Contracts\Documents\DocumentContract;
use Crater\Contracts\Documents\HasAmounts;
use Crater\Contracts\Documents\HasHistoricalSnapshots;
use Crater\Contracts\Documents\HasItems;
use Crater\Contracts\Documents\HasTaxes;
use Crater\Contracts\Documents\Printable;
use Crater\Contracts\Documents\SerialNumbered;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan 09 §F11 + plan 05 — DeliveryNote model.
 *
 * Único modelo NUEVO creado por el plan 09, por lo que adopta
 * `implements` desde el día 1 (Estrategia A del PR-02 / F1 permite
 * que los modelos nuevos se sumen al contrato sin esperar a que
 * los viejos adopten las capacidades). El test
 * `ContractComplianceTest` solo verifica que Invoice/Estimate/
 * RecurringInvoice NO implementan (aún); este modelo sí.
 *
 * Capacidades adoptadas (matriz plan 06 §paso-5 §3, columna
 * DeliveryNote):
 * - DocumentContract (raíz, ciclo de conversión)
 * - HasAmounts (totales para conversión)
 * - HasItems (líneas de DeliveryNoteItem)
 * - HasTaxes (taxes polimórficas, plan 06 §paso-2 §3)
 * - SerialNumbered (numeración por empresa)
 * - HasHistoricalSnapshots (cliente vivo para el PDF)
 *
 * Capacidades NO adoptadas:
 * - Printable: el plan 11 menciona PDF pero `GenerateDeliveryNotePdfJob`
 *   queda como shell. Se introduce con E10 fuera del scope de F11.
 * - Payable: solo Invoice lo implementa (matriz §3).
 * - Mailable, Billable (entrada de factura): converter los emite.
 * - Deliverable: el `DeliveryNote` ES el deliverable; no necesita
 *   implementar la interfaz conceptual (matriz §3 lo declara así).
 */
class DeliveryNote extends Model implements DocumentContract, HasAmounts, HasItems, HasTaxes, Printable, Billable, SerialNumbered, HasHistoricalSnapshots
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_DELIVERED = 'DELIVERED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $guarded = ['id'];

    protected $dates = [
        'created_at',
        'updated_at',
        'delivery_date',
        'due_date',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'formattedDeliveryDate',
        'deliveryNotePdfUrl',
        'customerSnapshot',
        'billingAddress',
        'shippingAddress',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class, 'delivery_note_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'delivery_note_id');
    }

    /**
     * Si este DeliveryNote ya fue convertido a Invoice, devuelve la
     * Invoice relacionada. Único 1:1 gracias al UNIQUE en
     * `invoices.delivery_note_id`.
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'delivery_note_id');
    }

    public function documentType(): string
    {
        return 'delivery-note';
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function getPreviousStatus(): string
    {
        return $this->status;
    }

    public function getFormattedNotes(): ?string
    {
        return $this->notes;
    }

    public function persistedTotals(): \Crater\DataObjects\Documents\DocumentTotals
    {
        return \Crater\DataObjects\Documents\DocumentTotals::fromModel($this);
    }

    public function totals(): \Crater\DataObjects\Documents\DocumentTotals
    {
        return $this->persistedTotals();
    }

    public function recompute(): void
    {
        // Recalculo minimo: totals = sub_total + tax. La cantidad positiva
        // y agregaciones mas finas se haran en una fase futura si
        // aumenta la complejidad.
        $this->total = (int) ($this->sub_total ?? 0) + (int) ($this->tax ?? 0);
        $this->save();
    }

    public function syncItems(iterable $items): void
    {
        $this->items()->delete();
        foreach ($items as $payload) {
            $this->items()->create((array) $payload);
        }
    }

    public function syncTaxes(iterable $taxes): void
    {
        $this->taxes()->delete();
        foreach ($taxes as $payload) {
            $payload['delivery_note_id'] = $this->id;
            $this->taxes()->create((array) $payload);
        }
    }

    public function nextNumber(): string
    {
        $serial = (new SerialNumberFormatter())
            ->setModel(new DeliveryNote())
            ->setCompany((int) $this->company_id)
            ->setCustomer((int) $this->customer_id)
            ->setNextNumbers();

        return $serial->getNextNumber();
    }

    public function getSerialContext(): array
    {
        return [
            'model' => self::class,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
        ];
    }

    public function customerSnapshot(): \Crater\DataObjects\Documents\CustomerSnapshot
    {
        return app(\Crater\Services\Documents\DocumentSnapshotter::class)
            ->snapshotCustomer($this->customer);
    }

    public function billingAddress(): ?\Crater\DataObjects\Documents\AddressSnapshot
    {
        if (! $this->customer) {
            return null;
        }
        $billing = method_exists($this->customer, 'billingAddress')
            ? $this->customer->billingAddress()->first()
            : null;

        return app(\Crater\Services\Documents\DocumentSnapshotter::class)
            ->snapshotAddress(
                $billing,
                \Crater\DataObjects\Documents\AddressSnapshot::TYPE_BILLING
            );
    }

    public function shippingAddress(): ?\Crater\DataObjects\Documents\AddressSnapshot
    {
        if (! $this->customer) {
            return null;
        }
        $shipping = method_exists($this->customer, 'shippingAddress')
            ? $this->customer->shippingAddress()->first()
            : null;

        return app(\Crater\Services\Documents\DocumentSnapshotter::class)
            ->snapshotAddress(
                $shipping,
                \Crater\DataObjects\Documents\AddressSnapshot::TYPE_SHIPPING
            );
    }

    public function getFormattedCreatedAtAttribute(): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getFormattedDeliveryDateAttribute(): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->delivery_date)->format($dateFormat);
    }

    /**
     * URL publica del PDF (hash via Hashids). F11 entrega el shell;
     * la ruta `/delivery-notes/pdf/{hash}` se introduce con E10.
     */
    public function getDeliveryNotePdfUrlAttribute(): ?string
    {
        if (! $this->unique_hash) {
            return null;
        }

        return url('/delivery-notes/pdf/'.$this->unique_hash);
    }

    public function alreadyBilled(): bool
    {
        return $this->invoice()->exists();
    }

    public function markAsDelivered(): void
    {
        if ($this->alreadyBilled()) {
            throw new \RuntimeException('Cannot mark a billed DeliveryNote as delivered.');
        }
        $this->status = self::STATUS_DELIVERED;
        $this->save();
    }

    public function cancel(): void
    {
        if ($this->alreadyBilled()) {
            throw new \RuntimeException('Cannot cancel a billed DeliveryNote.');
        }
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function deliveryStatus(): string
    {
        return $this->status;
    }

    public function deliveryAddress(): \Crater\DataObjects\Documents\AddressSnapshot
    {
        return new \Crater\DataObjects\Documents\AddressSnapshot(
            name: 'Delivery destination',
            countryName: null,
            state: null,
            city: null,
            street1: $this->delivery_address,
            type: \Crater\DataObjects\Documents\AddressSnapshot::TYPE_DELIVERY,
        );
    }

    // -----------------------------------------------------------------
    // Printable (plan 09 §F1): shell básico. La generación de PDF
    // visual se introduce con E10 fuera del scope de F11.
    // -----------------------------------------------------------------
    public function pdfView(): string
    {
        return 'app.pdf.delivery_note.delivery_note1';
    }

    public function getPDFData(): array
    {
        return [
            'delivery_note' => $this->toArray(),
            'customer' => $this->customer?->toArray(),
            'company' => Company::find($this->company_id)?->toArray(),
        ];
    }

    public function getGeneratedPDF(string $collectionName): ?array
    {
        return null;
    }

    public function billToInvoice(): Invoice
    {
        if ($this->alreadyBilled()) {
            return $this->invoice()->first();
        }

        if ($this->status !== self::STATUS_DELIVERED) {
            throw new \RuntimeException(
                'Only DELIVERED DeliveryNotes can be converted to Invoice.'
            );
        }

        return app(\Crater\Services\Documents\DocumentConverterRegistry::class)
            ->get('delivery-note', 'invoice')
            ->convert($this);
    }
}

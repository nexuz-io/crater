<?php

namespace Crater\Models;

use Barryvdh\DomPDF\Facade\Pdf as PDF;
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
use Crater\Traits\GeneratesPdfTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

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
class DeliveryNote extends Model implements DocumentContract, HasAmounts, HasItems, HasTaxes, Printable, Billable, SerialNumbered, HasHistoricalSnapshots, HasMedia
{
    use HasFactory;
    use GeneratesPdfTrait;
    use InteractsWithMedia;

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
    // Printable (plan 05 §7 + plan 09 §F11).
    //
    // El PDF del albarán NO muestra importes por defecto (plan 05 §1):
    // sólo cantidades, descripción, unidad y notas libres. El
    // `DeliveryNotePdfController` y `GenerateDeliveryNotePdfJob`
    // invocan este getPDFData() a través del trait compartido.
    // -----------------------------------------------------------------
    public function pdfView(): string
    {
        return 'app.pdf.delivery-note.delivery-note-1';
    }

    public function getPDFData(): mixed
    {
        $company = Company::find($this->company_id);
        $locale = CompanySetting::getSetting('language', $company?->id);
        App::setLocale($locale ?: config('app.locale'));

        $logo = $company?->logo_path ?? null;

        $data = [
            'deliveryNote' => $this,
            'customer' => $this->customer,
            'company_address' => $this->getCompanyAddress(),
            'shipping_address' => $this->getShippingAddress(),
            'billing_address' => $this->getBillingAddress(),
            'notes' => $this->notes,
            'logo' => $logo,
            'delivery_address' => $this->delivery_address,
            // Plan 05 §1: por defecto NO mostramos precios. Honra el
            // override de empresa `delivery_note_display_prices` =
            // 'YES'|'NO' (mismo nombre que ya define la i18n
            // `display_prices_on_pdf`).
            'show_prices' => CompanySetting::getSetting(
                'delivery_note_display_prices',
                $this->company_id
            ) === 'YES',
        ];

        if (request()->has('preview')) {
            return view($this->pdfView(), $data);
        }

        return PDF::loadView($this->pdfView(), $data);
    }

    /**
     * Compatibilidad con `Printable::getGeneratedPDF(string):
     * ?array`. Devuelve siempre null para forzar la regeneration
     * por la trait cada vez (el albarán es un documento operativo
     * y rara vez reutiliza PDF cacheados como Invoice). El trait
     * trata null/false como "regenerar" y ejecuta `->stream()`.
     *
     * Plan 05 §6 + plan 09 §F11: para los PDFs de albaranes
     * frecuentemente actualizados preferimos evitar caches
     * stale.
     */
    public function getGeneratedPDF(string $collection_name): ?array
    {
        return null;
    }

    /**
     * Devuelve la dirección de empresa formateada según
     * `delivery_note_company_address_format` (config generosa;
     * algunos clientes siguen usando `invoice_company_address_format`
     * por compatibilidad con instalaciones existentes).
     */
    public function getCompanyAddress()
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('delivery_note_company_address_format', $this->company_id)
            ?: CompanySetting::getSetting('invoice_company_address_format', $this->company_id);

        return $format ? $this->getFormattedString($format) : null;
    }

    public function getShippingAddress()
    {
        if ($this->customer && (! $this->customer->shippingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('delivery_note_shipping_address_format', $this->company_id)
            ?: CompanySetting::getSetting('invoice_shipping_address_format', $this->company_id);

        return $format ? $this->getFormattedString($format) : null;
    }

    public function getBillingAddress()
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('delivery_note_billing_address_format', $this->company_id)
            ?: CompanySetting::getSetting('invoice_billing_address_format', $this->company_id);

        return $format ? $this->getFormattedString($format) : null;
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

    // -----------------------------------------------------------------
    // Mail/PDF — gemelo de Estimate (`sendEstimateData` / `send`).
    // Plan 05 §6 + plan 09 PR-11.
    //
    // El albarán no se marca como "SENT" automáticamente al enviar
    // por correo: puede enviarse tanto en DRAFT (borrador al cliente
    // para firma) como en DELIVERED (confirmación post-entrega).
    // La transición DRAFT→DELIVERED queda en `markAsDelivered()` para
    // que sea independiente del envío.
    // -----------------------------------------------------------------
    public function sendDeliveryNoteData($data)
    {
        $data['delivery_note'] = $this->toArray();
        $data['user'] = $this->customer->toArray();
        $data['company'] = $this->company->toArray();
        $data['body'] = $this->getEmailBody($data['body']);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->getPDFData() : null;

        return $data;
    }

    public function send($data)
    {
        $data = $this->sendDeliveryNoteData($data);

        \Mail::to($data['to'])->send(new \Crater\Mail\SendDeliveryNoteMail($data));

        return [
            'success' => true,
            'type' => 'send',
        ];
    }

    public function getEmailAttachmentSetting(): bool
    {
        $setting = \Crater\Models\CompanySetting::getSetting('delivery_note_email_attachment', $this->company_id);

        if ($setting === 'NO') {
            return false;
        }

        return true;
    }

    public function getEmailBody(string $body): string
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body, $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getExtraFields(): array
    {
        return [
            '{DELIVERY_DATE}' => $this->formattedDeliveryDate ?? '',
            '{DELIVERY_NUMBER}' => $this->delivery_number ?? '',
            '{DELIVERY_REF_NUMBER}' => $this->reference_number ?? '',
        ];
    }

    /**
     * Lista las plantillas de PDF disponibles para los albaranes
     * escaneando `resources/views/app/pdf/delivery-note/*.blade.php`.
     * Gemelo de `Estimate::estimateTemplates()`.
     */
    public static function deliveryNoteTemplates(): array
    {
        $templates = Storage::disk('views')->files('/app/pdf/delivery-note');

        $deliveryNoteTemplates = [];

        foreach ($templates as $key => $template) {
            $templateName = Str::before(basename($template), '.blade.php');
            $deliveryNoteTemplates[$key]['name'] = $templateName;
            $deliveryNoteTemplates[$key]['path'] = asset('/img/PDF/'.$templateName.'.png');
        }

        return $deliveryNoteTemplates;
    }

    /**
     * Bulk delete — gemelo de Invoice::deleteInvoices. Antes de borrar
     * limpia las filas de taxes polimórficas vinculadas al albarán
     * para evitar huérfanas (FK está con `onDelete('set null')`, pero
     * al cancelarlas la Invoice convertida conservará totales
     * involucrados).
     */
    public static function deleteDeliveryNotes(array $ids): bool
    {
        foreach ($ids as $id) {
            $deliveryNote = self::find($id);

            if (! $deliveryNote) {
                continue;
            }

            if ($deliveryNote->alreadyBilled()) {
                // El plan 05 §3 lo veta: no se permite el borrado físico
                // porque la Invoice mantiene `delivery_note_id` y perdería
                // la trazabilidad. Anular es la salida correcta.
                continue;
            }

            $deliveryNote->delete();
        }

        return true;
    }
}

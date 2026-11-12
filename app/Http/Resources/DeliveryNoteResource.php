<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan 09 §F11a — JSON contract for the DeliveryNote API.
 *
 * Sigue el patrón del resto de resources (InvoiceResource.php) para
 * que el frontend pueda tratar /delivery-notes con la misma shape
 * estructural. Sin relación con el `DocumentContract` (eso es el
 * shape del modelo, no del payload HTTP).
 *
 * Campos anadidos respeto a `delivery_notes.*` que la SPA consume:
 *  - `formatted_*` con el formato de fecha de la company.
 *  - `items` (lazy-loaded si existen) — null cuando no.
 *  - `taxes` (idem).
 *  - `customer` (resumen) — full payload se obtiene via el endpoint
 *    del CustomerController.
 *  - `allow_edit`, `allow_delet` — flags para que el cliente oculte
 *    botones cuando el documento ya está DELIVERED/CANCELLED.
 *  - `deliveryNotePdfUrl` — shell del F11; null hasta que se monte
 *    la vista Blade.
 *  - `already_billed` — true cuando ya existe `invoices.delivery_note_id`
 *    apuntando a este DeliveryNote; courtesy flag para el UI.
 *
 * El `toArray($request)` se mantiene minimo para no exponer columnas
 * internas sin necesidad (`notes` se devuelve sin `toArray`
 * recursivo para evitar loop infinito de Customer).
 */
class DeliveryNoteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'creator_id' => $this->creator_id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'currency_id' => $this->currency_id,

            'delivery_number' => $this->delivery_number,
            'reference_number' => $this->reference_number,
            'template_name' => $this->template_name,

            'delivery_date' => $this->delivery_date,
            'due_date' => $this->due_date,
            'delivery_address' => $this->delivery_address,

            'status' => $this->status,
            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,

            'sub_total' => $this->sub_total,
            'total' => $this->total,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'discount_val' => $this->discount_val,
            'discount_type' => $this->discount_type,
            'tax_per_item' => $this->tax_per_item,
            'discount_per_item' => $this->discount_per_item,
            'sales_tax_type' => $this->sales_tax_type,
            'sales_tax_address_type' => $this->sales_tax_address_type,

            'exchange_rate' => $this->exchange_rate,
            'base_total' => $this->base_total,
            'base_sub_total' => $this->base_sub_total,
            'base_tax' => $this->base_tax,
            'base_discount_val' => $this->base_discount_val,

            'unique_hash' => $this->unique_hash,
            'sequence_number' => $this->sequence_number,
            'customer_sequence_number' => $this->customer_sequence_number,

            'formatted_created_at' => $this->formattedCreatedAt ?? null,
            'formatted_delivery_date' => $this->formattedDeliveryDate ?? null,

            // Flags operacionales — reflejan mat plan 06 §paso-5 "Por
            // modificar tras entrega": si status != DRAFT, no se
            // permite update ni delete.
            'allow_edit' => $this->status === \Crater\Models\DeliveryNote::STATUS_DRAFT,
            'allow_delete' => $this->status === \Crater\Models\DeliveryNote::STATUS_DRAFT,
            // Bifurcación enviada vs no enviada. Mientras Mailable
            // este shell en F11, `allow_send` se mantiene pero el
            // endpoint real no emite correo.
            'allow_send' => $this->status === \Crater\Models\DeliveryNote::STATUS_DRAFT,

            'already_billed' => $this->alreadyBilled(),
            'delivery_note_pdf_url' => $this->deliveryNotePdfUrl,

            // Items / taxes — opcionales, null cuando el cliente del
            // API no las eager-load.
            'items' => \Crater\Http\Resources\DeliveryNoteItemResource::collection($this->whenLoaded('items')),
            'taxes' => \Crater\Http\Resources\TaxResource::collection($this->whenLoaded('taxes')),

            // Customer — pivot del listado admin:
            //   - Index.vue cell-name:  row.data.customer.name
            //   - Index.vue cell-total: row.data.customer.currency
            //
            // `$this->when(boolean, callback)` ejecuta el callback (con
            // un CustomerResource) cuando el boolean es true; en otro
            // caso la key se omite del payload (MissingValue). No
            // usamos `whenLoaded` porque el controller index no
            // carga eager — sólo emite`.customer()->exists()` que cae en
            // lazy-load; eso es N+1 aceptable para Crater (mismo trato
            // que InvoiceResource.php). El controller de admin hace
            // `->with('customer')` para acortar esto a 2 queries.
            'customer' => $this->when($this->customer()->exists(), function () {
                return new \Crater\Http\Resources\CustomerResource($this->customer);
            }),
        ];
    }
}

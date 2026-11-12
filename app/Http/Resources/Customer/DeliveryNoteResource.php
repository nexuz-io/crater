<?php

namespace Crater\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan 05 §8 + plan 09 §F11 — vista JSON del DeliveryNote para el
 * portal de cliente. Equivalente a `EstimateResource` (versión
 * cliente), sin exponer campos administrativos (creator_id,
 * company_id, sequence_number, etc.) — sólo lo que el cliente
 * necesita para entender el documento.
 */
class DeliveryNoteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'delivery_date' => $this->delivery_date,
            'delivery_number' => $this->delivery_number,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'total' => $this->total,
            'tax' => $this->tax,
            'sub_total' => $this->sub_total,
            'notes' => $this->notes,
            'delivery_address' => $this->delivery_address,
            'currency_id' => $this->currency_id,
            'unique_hash' => $this->unique_hash,
            'customer_id' => $this->customer_id,
            'already_billed' => $this->alreadyBilled(),

            'formatted_delivery_date' => $this->formattedDeliveryDate,
            'delivery_note_pdf_url' => $this->deliveryNotePdfUrl,

            'items' => $this->when($this->items()->exists(), function () {
                return DeliveryNoteItemResource::collection($this->items);
            }),
            'customer' => $this->when($this->customer()->exists(), function () {
                return new CustomerResource($this->customer);
            }),
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
            'currency' => $this->when($this->currency()->exists(), function () {
                return new CurrencyResource($this->currency);
            }),
        ];
    }
}

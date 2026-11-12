<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'delivery_note_id' => $this->delivery_note_id,
            'item_id' => $this->item_id,
            'company_id' => $this->company_id,

            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => (float) $this->quantity,

            'price' => $this->price,
            'discount_type' => $this->discount_type,
            'discount_val' => $this->discount_val,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,

            'exchange_rate' => $this->exchange_rate,
            'base_price' => $this->base_price,
            'base_discount_val' => $this->base_discount_val,
            'base_tax' => $this->base_tax,
            'base_total' => $this->base_total,
        ];
    }
}

<?php

namespace Crater\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'item_id' => $this->item_id,
            'delivery_note_id' => $this->delivery_note_id,
            'company_id' => $this->company_id,
        ];
    }
}

<?php

namespace Crater\Http\Requests;

use Crater\Models\DeliveryNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryNoteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'delivery_date' => ['required', 'date'],
            'delivery_number' => [
                'required',
                Rule::unique('delivery_notes')->where('company_id', $this->header('company')),
            ],
            'customer_id' => ['required', 'integer'],
            'currency_id' => ['nullable', 'integer'],
            'exchange_rate' => ['nullable'],
            'reference_number' => ['nullable', 'string'],
            'template_name' => ['nullable', 'string'],
            'sub_total' => ['nullable'],
            'total' => ['nullable'],
            'tax' => ['nullable'],
            'tax_per_item' => ['nullable', 'string'],
            'discount' => ['nullable'],
            'discount_val' => ['nullable'],
            'discount_type' => ['nullable', 'string'],
            'discount_per_item' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([
                DeliveryNote::STATUS_DRAFT,
                DeliveryNote::STATUS_DELIVERED,
                DeliveryNote::STATUS_CANCELLED,
            ])],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.unit' => ['nullable', 'string'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'taxes' => ['nullable', 'array'],
        ];
    }
}

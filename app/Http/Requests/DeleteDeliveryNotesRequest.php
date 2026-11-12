<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plan 05 §3 — bulk delete de DeliveryNote. Sólo borra albaranes
 * NO facturados (los `already_billed()` quedan intactos por
 * trazabilidad contable). Gemelo de DeleteEstimatesRequest.
 */
class DeleteDeliveryNotesRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ids' => [
                'required',
                'array',
            ],
            'ids.*' => [
                'required',
                Rule::exists('delivery_notes', 'id'),
            ],
        ];
    }
}

<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Plan 05 §6 + plan 09 PR-11 — validación del payload de envío
 * por correo de un DeliveryNote. Gemelo de SendEstimatesRequest.
 *
 * El email puede ser cualquier RFC válido. Los campos `subject`/`body`
 * admiten los placeholders declarados en
 * `DeliveryNote::getExtraFields()` (`{DELIVERY_DATE}`,
 * `{DELIVERY_NUMBER}`, `{DELIVERY_REF_NUMBER}`) más los heredados
 * del `GeneratesPdfTrait::getFieldsArray()` (direcciones, contacto,
 * empresa, custom fields).
 */
class SendDeliveryNoteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'subject' => [
                'required',
            ],
            'body' => [
                'required',
            ],
            'from' => [
                'required',
                'email',
            ],
            'to' => [
                'required',
                'email',
            ],
        ];
    }
}

<?php

namespace Crater\Http\Requests;

use Crater\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function prepareForValidation()
    {
        // A service is inactive unless the request explicitly activates it.
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            // `brand_id` arrives as a string from <select> options that
            // represent "Sin marca" as ''. Coerce empty strings to a
            // real null so the nullable rule + UPDATE set NULL contract
            // work uniformly.
            'brand_id' => $this->input('brand_id') === '' || $this->input('brand_id') === null
                ? null
                : $this->input('brand_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
            ],
            'price' => [
                'required',
            ],
            'unit_id' => [
                'nullable',
            ],
            'description' => [
                'nullable',
            ],
            'deliverables' => [
                'nullable',
                'string',
            ],
            'delivery_delay' => [
                'nullable',
                'string',
                'max:255',
            ],
            'terms' => [
                'nullable',
                'string',
            ],
            'default_quantity' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'brand_id' => [
                'nullable',
                'integer',
                // A brand id is only valid if it belongs to the currently
                // selected company. The DB FK also enforces this, but
                // validating here gives the client a clear 422 error
                // instead of a generic integrity violation.
                Rule::exists((new Brand())->getTable(), 'id')
                    ->where('company_id', $this->header('company')),
            ],
        ];
    }
}

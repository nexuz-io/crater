<?php

namespace Crater\Http\Requests;

use Crater\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    /**
     * Brand mutations are driven by the controller's authorize()
     * checks (policy) — the request itself does not gate access.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $data = [
            'name' => [
                'required',
                'string',
                'max:255',
                // Case-insensitive uniqueness within the company. The
                // brands_company_name_unique index in the `brands`
                // migration mirrors this constraint for engines that
                // resolve it natively (MySQL / PostgreSQL); SQLite
                // falls back to validation-only enforcement, which is
                // harmless because every write flows through this
                // method.
                Rule::unique('brands')
                    ->where('company_id', $this->header('company'))
                    ->where(fn ($q) => $q->whereRaw(
                        'name_normalized = ?',
                        [Brand::normalizeName((string) $this->input('name'))]
                    )),
            ],
        ];

        // PUT exposes the {brand} route parameter so the unique
        // check can ignore the row being edited.
        if ($this->getMethod() == 'PUT') {
            $data['name'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('brands')
                    ->ignore($this->route('brand'), 'id')
                    ->where('company_id', $this->header('company'))
                    ->where(fn ($q) => $q->whereRaw(
                        'name_normalized = ?',
                        [Brand::normalizeName((string) $this->input('name'))]
                    )),
            ];
        }

        return $data;
    }

    /**
     * Centralises input normalisation with the model's contract so the
     * BrandFactory + tests don't accumulate divergent logic. Returns
     * the validated payload plus the trimmed display name and the
     * derived comparison key.
     */
    public function getBrandPayload(): array
    {
        $name = Brand::cleanName((string) $this->validated()['name']);

        return [
            'name' => $name,
            'company_id' => (int) $this->header('company'),
        ];
    }
}

<?php

namespace Crater\Http\Requests;

use Crater\Models\ItemFamily;
use Crater\Services\ItemFamilyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemFamilyRequest extends FormRequest
{
    /**
     * Tree-shape validation lives in ItemFamilyService: cycles,
     * sibling-uniqueness, and item-attachment reject paths. The
     * request just translates the HTTP body into `name` / `parent_id`
     * and lets the service decide whether the move is legal.
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
            ],
            'parent_id' => [
                'nullable',
                Rule::exists((new ItemFamily())->getTable(), 'id')
                    ->where('company_id', $this->header('company')),
            ],
        ];

        // On PUT we only enforce that the new name, if changed, is
        // distinct among siblings AFTER taking the new parent into
        // account. The end-to-end check (and cycle / cross-company
        // guards) lives in ItemFamilyService::update.
        if ($this->getMethod() == 'PUT') {
            $data['name'] = [
                'required',
                'string',
                'max:255',
                Rule::unique((new ItemFamily())->getTable(), 'name')
                    ->ignore($this->route('item_family'), 'id')
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $data;
    }

    /**
     * Returns the validated payload as the service expects it.
     */
    public function getFamilyPayload(): array
    {
        return collect($this->validated())
            ->only(['name', 'parent_id'])
            ->toArray();
    }

    /**
     * Reference to the service so the controller can delegate
     * cleanly without juggling two layers.
     */
    public function service(): ItemFamilyService
    {
        return app(ItemFamilyService::class);
    }
}

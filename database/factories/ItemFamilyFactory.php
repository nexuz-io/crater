<?php

namespace Database\Factories;

use Crater\Models\Company;
use Crater\Models\ItemFamily;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFamilyFactory extends Factory
{
    /**
     * The factory's corresponding model.
     *
     * @var string
     */
    protected $model = ItemFamily::class;

    /**
     * @return array
     */
    public function definition()
    {
        $companyId = Company::first()?->id ?? User::find(1)?->companies()->first()->id;
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'name_normalized' => ItemFamily::normalizeName($name),
            'company_id' => $companyId,
            'parent_id' => null,
        ];
    }

    /**
     * Convenience state to attach the family under a known parent
     * within the same company. The caller passes the resolved
     * parent record.
     */
    public function childOf(ItemFamily $parent)
    {
        return $this->state(function () use ($parent) {
            return [
                'company_id' => $parent->company_id,
                'parent_id' => $parent->id,
            ];
        });
    }
}

<?php

namespace Database\Factories;

use Crater\Models\Company;
use Crater\Models\ItemFamily;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFamilyFactory extends Factory
{
    protected $model = ItemFamily::class;

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

    public function childOf(ItemFamily $parent)
    {
        return $this->state(function () use ($parent) {
            return [
                'company_id' => $parent->company_id,
                'parent_id' => $parent->id,
            ];
        });
    }

    /**
     * Plan 09 hotfix: tras aplicar overrides (`create(['name' => 'X'])`),
     * recalculamos `name_normalized` desde el `name` final. Sin esto,
     * el override de `name` deja `name_normalized` con el valor
     * random de la fakería original y rompe el chequeo de sibling-
     * uniqueness en ItemFamilyService::assertNoSiblingWithName.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ItemFamily $family) {
            $family->name_normalized = ItemFamily::normalizeName(
                (string) ($family->name ?? '')
            );
        });
    }
}

<?php

namespace Database\Factories;

use Crater\Models\Brand;
use Crater\Models\Company;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    /**
     * The factory's corresponding model.
     *
     * @var string
     */
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Resolve the default company lazily so the seeder's order is
        // not a dependency of this factory. Brand::normalizeName()
        // mirrors the production wire-up, keeping factory state
        // identical to what the controller persists.
        $companyId = Company::first()?->id ?? User::find(1)?->companies()->first()->id;
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'name_normalized' => Brand::normalizeName($name),
            'company_id' => $companyId,
        ];
    }
}

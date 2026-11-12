<?php

namespace Database\Factories;

use Crater\Models\Company;
use Crater\Models\Customer;
use Crater\Models\DeliveryNote;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeliveryNoteFactory extends Factory
{
    protected $model = DeliveryNote::class;

    public function definition()
    {
        return [
            'creator_id' => User::find(1)?->id ?? null,
            'company_id' => User::find(1)?->companies()->first()?->id ?? Company::factory(),
            'customer_id' => Customer::factory(),
            'currency_id' => \Crater\Models\Currency::find(1)?->id ?? null,
            'delivery_number' => 'DN-'.Str::upper(Str::random(8)),
            'reference_number' => 'DN-ref-'.Str::random(4),
            'template_name' => 'delivery_note1',
            'delivery_date' => now()->format('Y-m-d'),
            'due_date' => null,
            'delivery_address' => $this->faker->streetAddress(),
            'notes' => $this->faker->text(80),
            'status' => DeliveryNote::STATUS_DRAFT,
            'sub_total' => $this->faker->randomDigitNotNull,
            'total' => $this->faker->randomDigitNotNull,
            'tax' => $this->faker->randomDigitNotNull,
            'discount' => $this->faker->randomDigitNotNull,
            'discount_val' => $this->faker->randomDigitNotNull,
            'discount_type' => 'percentage',
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
            'sales_tax_type' => 'none',
            'sales_tax_address_type' => 'none',
            'exchange_rate' => $this->faker->randomDigitNotNull,
            'unique_hash' => 'poc-f11-'.Str::random(10),
            'sequence_number' => $this->faker->randomDigitNotNull,
            'customer_sequence_number' => $this->faker->randomDigitNotNull,
        ];
    }
}

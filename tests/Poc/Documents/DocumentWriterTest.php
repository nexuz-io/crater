<?php

use Crater\Models\Company;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Crater\Models\TaxType;
use Illuminate\Support\Facades\DB;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;

/**
 * T2 — Atomicidad DocumentWriter (PoC step 7 §4 / HR7).
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('DocumentWriter es resoluble desde el container', function () {
    $writer = app(\Crater\Poc\Documents\Services\DocumentWriter::class);
    expect($writer)->toBeInstanceOf(\Crater\Poc\Documents\Services\DocumentWriter::class);
});

it('DocumentWriter::sync acepta invoices reales con items y taxes', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoice = Invoice::create([
        'creator_id' => $user->id,
        'invoice_date' => '2026-11-11',
        'due_date' => null,
        'invoice_number' => 'POC-T2-'.time(),
        'sequence_number' => 1,
        'customer_sequence_number' => 1,
        'reference_number' => 'POC-T2',
        'customer_id' => $customer->id,
        'company_id' => $company->id,
        'template_name' => 'invoice1',
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => 100,
        'total' => 105,
        'tax' => 5,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'due_amount' => 105,
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'notes' => 'poc',
        'unique_hash' => 'poc-hash-'.time(),
        'currency_id' => $currency->id,
    ]);

    $writer = app(\Crater\Poc\Documents\Services\DocumentWriter::class);

    $items = [[
        'name' => 'Serv-PoC',
        'quantity' => 1.0,
        'price' => 100,
        'tax' => 5,
        'total' => 105,
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'company_id' => $company->id,
    ]];
    DB::table('tax_types')->insert([
        'name' => 'POC-TVA',
        'percent' => 5,
        'compound_tax' => 0,
        'collective_tax' => 0,
    ]);

    $taxes = [[
        'tax_type_id' => TaxType::where('name', 'POC-TVA')->first()->id,
        'name' => 'TVA',
        'amount' => 5,
        'percent' => 5,
        'compound_tax' => 0,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
        'base_amount' => 5,
    ]];

    $result = $writer->sync($invoice, $items, $taxes);

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->id)->toBe($invoice->id);
    $this->assertDatabaseCount('invoice_items', 1);
    $this->assertDatabaseCount('taxes', 1);
});

it('DocumentTotalsCalculator::for devuelve DocumentTotals ricos', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoice = Invoice::create([
        'creator_id' => $user->id,
        'invoice_date' => '2026-11-11',
        'due_date' => null,
        'invoice_number' => 'POC-T2-CALC-'.time(),
        'sequence_number' => 1,
        'customer_sequence_number' => 1,
        'reference_number' => 'POC-T2-CALC',
        'customer_id' => $customer->id,
        'company_id' => $company->id,
        'template_name' => 'invoice1',
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => 1000,
        'total' => 1050,
        'tax' => 50,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'discount_type' => null,
        'discount' => 0,
        'discount_val' => 0,
        'due_amount' => 1050,
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'notes' => 'poc',
        'unique_hash' => 'poc-calchash-'.time(),
        'currency_id' => $currency->id,
    ]);

    $calc = app(\Crater\Poc\Documents\Services\DocumentTotalsCalculator::class);
    $totals = $calc->for($invoice);

    expect($totals)->toBeInstanceOf(\Crater\Poc\Documents\Dto\DocumentTotals::class);
    expect($totals->subTotal)->toBe(1000);
    expect($totals->total)->toBe(1050);
    expect($totals->tax)->toBe(50);
});

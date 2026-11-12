<?php

use Crater\Models\Company;
use Crater\Models\Customer;
use Crater\Models\Currency;
use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\Invoice;
use Crater\Models\Tax;
use Crater\Models\TaxType;
use Crater\Models\User;
use Crater\Services\Documents\Converters\EstimateToInvoiceConverter;
use Crater\Services\Documents\DocumentConverterRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Plan 09 §F3 — T3 EstimateToInvoiceConverter (PoC step 7).
 *
 * Aserciones clave (matriz plan 06 §paso-5):
 *
 * - 28 campos de la Invoice derivados del Estimate.
 * - Taxes del estimate quedan desvinculados (estimate_id = null).
 * - `setNextNumbers` aplicado vía SerialNumberFormatter.
 * - `company_id` propagado desde el Estimate.
 * - `custom_fields` por item no incluidos (F7 los introduce).
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    // Auth::id() debe devolver algo — el controller original también
    // dependía de Auth::id(). En unit tests no tenemos sesión.
    Auth::shouldUse('web');
    $user = User::find(1);
    if ($user) {
        Auth::login($user);
    }

    // Asegurar un TaxType para que las FK de taxes satisfagan.
    if (TaxType::count() === 0) {
        TaxType::create([
            'name' => 'POC-F3-TVA',
            'percent' => 5,
            'compound_tax' => 0,
            'collective_tax' => 0,
        ]);
    }
});

it('DocumentConverterRegistry resuelve estimate->invoice por clave', function () {
    $registry = new DocumentConverterRegistry();
    $registry->register(new EstimateToInvoiceConverter());

    $converter = $registry->get('estimate', 'invoice');
    expect($converter)->toBeInstanceOf(EstimateToInvoiceConverter::class);
    expect($converter->sourceType())->toBe('estimate');
    expect($converter->targetType())->toBe('invoice');
});

it('DocumentConverterRegistry es singleton del container', function () {
    $r1 = app(DocumentConverterRegistry::class);
    $r2 = app(DocumentConverterRegistry::class);
    expect($r1)->toBe($r2);
    expect($r1->has('estimate', 'invoice'))->toBeTrue();
});

it('EstimateToInvoiceConverter rechaza origen no-Estimate', function () {
    $converter = new EstimateToInvoiceConverter();
    expect(fn () => $converter->convert(new \stdClass()))->toThrow(\InvalidArgumentException::class);
});

it('convert produce una Invoice con los 28 campos derivados del Estimate', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $estimate = Estimate::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'sub_total' => 500,
        'tax' => 25,
        'total' => 525,
        'discount_val' => 0,
        'discount' => 0,
        'discount_type' => 'percentage',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'template_name' => 'estimate1',
        'estimate_date' => '2026-11-11',
        'status' => Estimate::STATUS_DRAFT,
        'unique_hash' => 'poc-f3-'.time(),
    ]);

    // Item + tax del estimate.
    EstimateItem::factory()->create([
        'estimate_id' => $estimate->id,
        'name' => 'PoC-F3-Service',
        'price' => 500,
        'quantity' => 1,
        'tax' => 25,
        'total' => 525,
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'company_id' => $company->id,
        'exchange_rate' => 1,
    ]);

    $estimateTax = Tax::factory()->create([
        'estimate_id' => $estimate->id,
        'tax_type_id' => TaxType::first()->id,
        'name' => 'TVA',
        'amount' => 25,
        'percent' => 5,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
    ]);

    $estimate->refresh()->load('items', 'taxes');

    $converter = new EstimateToInvoiceConverter();
    $invoice = $converter->convert($estimate);

    expect($invoice)->toBeInstanceOf(Invoice::class);

    // Campos derivados del estimate.
    expect((int) $invoice->sub_total)->toBe(500);
    expect((int) $invoice->tax)->toBe(25);
    expect((int) $invoice->total)->toBe(525);
    expect((int) $invoice->due_amount)->toBe(525);
    expect((int) $invoice->base_total)->toBe(525);

    expect((int) $invoice->customer_id)->toBe((int) $customer->id);
    expect((int) $invoice->company_id)->toBe((int) $company->id);
    expect((int) $invoice->currency_id)->toBe((int) $currency->id);
    expect((int) $invoice->creator_id)->toBe((int) $user->id);

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);

    // `getInvoiceTemplateName` reemplaza 'estimate' → 'invoice' o
    // hace fallback a 'invoice1'. Verifica que NO devuelve
    // literalmente 'estimate1'.
    expect($invoice->template_name)->not->toBe('estimate1');
    expect($invoice->template_name)->toContain('invoice');

    // SerialNumberFormatter aplicado: invoice_number no es null,
    // sequence_number no es null.
    expect($invoice->invoice_number)->not->toBeNull();
    expect($invoice->sequence_number)->not->toBeNull();
    expect($invoice->customer_sequence_number)->not->toBeNull();
    // unique_hash con Hashids.
    expect($invoice->unique_hash)->not->toBeNull();
    expect(strlen($invoice->unique_hash))->toBeGreaterThan(0);
});

it('los items del estimate quedan como invoice_items del invoice', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $estimate = Estimate::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
        'template_name' => 'estimate1',
        'status' => Estimate::STATUS_DRAFT,
        'unique_hash' => 'poc-f3-items-'.time(),
    ]);

    EstimateItem::factory()->create([
        'estimate_id' => $estimate->id,
        'name' => 'Lin-1',
        'price' => 100,
        'quantity' => 1,
        'tax' => 0,
        'total' => 100,
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'company_id' => $company->id,
    ]);
    EstimateItem::factory()->create([
        'estimate_id' => $estimate->id,
        'name' => 'Lin-2',
        'price' => 200,
        'quantity' => 1,
        'tax' => 0,
        'total' => 200,
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'company_id' => $company->id,
    ]);

    $estimate->refresh()->load('items');

    $converter = new EstimateToInvoiceConverter();
    $invoice = $converter->convert($estimate);

    expect($invoice->items()->count())->toBe(2);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'name' => 'Lin-1',
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'name' => 'Lin-2',
    ]);
});

it('los taxes del estimate quedan con estimate_id = null en la invoice', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $estimate = Estimate::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
        'template_name' => 'estimate1',
        'status' => Estimate::STATUS_DRAFT,
        'unique_hash' => 'poc-f3-taxes-'.time(),
    ]);

    $estimateTax = Tax::factory()->create([
        'estimate_id' => $estimate->id,
        'tax_type_id' => TaxType::first()->id,
        'name' => 'F3-Tax',
        'amount' => 25,
        'percent' => 5,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
    ]);

    $estimate->refresh()->load('taxes');

    $converter = new EstimateToInvoiceConverter();
    $invoice = $converter->convert($estimate);

    expect($invoice->taxes()->count())->toBe(1);
    $invoiceTax = $invoice->taxes()->first();
    expect((int) $invoiceTax->invoice_id)->toBe((int) $invoice->id);
    expect($invoiceTax->estimate_id)->toBeNull(); // desvinculado
    expect((int) $invoiceTax->amount)->toBe(25);
    expect($invoiceTax->name)->toBe('F3-Tax');
});

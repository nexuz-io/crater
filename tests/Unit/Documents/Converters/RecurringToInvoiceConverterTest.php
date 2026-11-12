<?php

use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;
use Crater\Models\Tax;
use Crater\Models\TaxType;
use Crater\Models\User;
use Crater\Services\Documents\Converters\RecurringToInvoiceConverter;
use Crater\Services\Documents\DocumentConverterRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Plan 09 §F4 — T4 RecurringToInvoiceConverter (PoC step 7).
 *
 * Aserciones clave:
 *
 * - sourceType/targetType del converter.
 * - Port literal: la Invoice resultante propaga los campos del
 *   RecurringInvoice (sub_total, total, exchange_rate, recurring_invoice_id,
 *   template_name, etc.) y calcula base_*, customer_sequence_number, y
 *   unique_hash.
 * - Items del recurring se crean como invoice_items (vía Invoice::createItems).
 * - Taxes del recurring se propagan a la invoice (vía Invoice::createTaxes).
 * - Campos temporales: invoice_date = hoy, due_date = hoy + invoice_due_date_days
 *   (o 7 por defecto).
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Auth::shouldUse('web');
    $user = User::find(1);
    if ($user) {
        Auth::login($user);
    }

    if (TaxType::count() === 0) {
        TaxType::create([
            'name' => 'POC-F4-TVA',
            'percent' => 5,
            'compound_tax' => 0,
            'collective_tax' => 0,
        ]);
    }
});

it('DocumentConverterRegistry resuelve recurring-invoice->invoice por clave', function () {
    $registry = new DocumentConverterRegistry();
    $registry->register(new RecurringToInvoiceConverter(app('db.connection')));

    $converter = $registry->get('recurring-invoice', 'invoice');
    expect($converter)->toBeInstanceOf(RecurringToInvoiceConverter::class);
    expect($converter->sourceType())->toBe('recurring-invoice');
    expect($converter->targetType())->toBe('invoice');
});

it('RecurringToInvoiceConverter rechaza origen no-RecurringInvoice', function () {
    $converter = new RecurringToInvoiceConverter(app('db.connection'));
    expect(fn () => $converter->convert(new \stdClass()))->toThrow(\InvalidArgumentException::class);
});

it('container provee el converter y mantiene singleton registry', function () {
    $r1 = app(DocumentConverterRegistry::class);
    $r2 = app(DocumentConverterRegistry::class);
    expect($r1)->toBe($r2);
    $converter = $r1->get('recurring-invoice', 'invoice');
    expect($converter)->toBeInstanceOf(RecurringToInvoiceConverter::class);
});

it('convert produce una Invoice con totales, base_*, sequence_number y recurring_invoice_id correctos', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $recurring = RecurringInvoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'sub_total' => 800,
        'tax' => 40,
        'total' => 880,
        'due_amount' => 880,
        'discount' => 0,
        'discount_val' => 0,
        'discount_type' => 'percentage',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'template_name' => 'invoice1',
        'status' => RecurringInvoice::ACTIVE,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'frequency' => '0 0 1 * *', // cada 1 del mes
        'limit_by' => RecurringInvoice::NONE,
        'limit_count' => null,
        'limit_date' => null,
        'next_invoice_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'send_automatically' => false,
    ]);

    $converter = app(DocumentConverterRegistry::class)->get('recurring-invoice', 'invoice');
    $invoice = $converter->convert($recurring);

    expect($invoice)->toBeInstanceOf(Invoice::class);

    // Campos derivados del recurring.
    expect((int) $invoice->sub_total)->toBe(800);
    expect((int) $invoice->tax)->toBe(40);
    expect((int) $invoice->total)->toBe(880);
    expect((int) $invoice->due_amount)->toBe(880);
    expect((int) $invoice->recurring_invoice_id)->toBe((int) $recurring->id);

    expect((int) $invoice->customer_id)->toBe((int) $customer->id);
    expect((int) $invoice->company_id)->toBe((int) $company->id);
    expect((int) $invoice->currency_id)->toBe((int) $currency->id);
    expect((int) $invoice->creator_id)->toBe((int) $user->id);

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);

    // base_* derivados del exchange_rate.
    expect((int) $invoice->base_total)->toBe(880);
    expect((int) $invoice->base_sub_total)->toBe(800);
    expect((int) $invoice->base_tax)->toBe(40);
    expect((int) $invoice->base_due_amount)->toBe(880);

    // Serialización + unique_hash.
    expect($invoice->invoice_number)->not->toBeNull();
    expect($invoice->sequence_number)->not->toBeNull();
    expect($invoice->customer_sequence_number)->not->toBeNull();
    expect($invoice->unique_hash)->not->toBeNull();
    expect(strlen($invoice->unique_hash))->toBeGreaterThan(0);

    // invoice_date = hoy, due_date = hoy + 7 días (default).
    expect(Carbon\Carbon::parse($invoice->invoice_date)->isToday())->toBeTrue();
    $expectedDue = Carbon\Carbon::today()->addDays(7)->format('Y-m-d');
    expect($invoice->due_date->format('Y-m-d'))->toBe($expectedDue);
});

it('los items del recurring quedan como invoice_items', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    // Limpiar invoice_items globales para evitar que el hasMany sin
    // filtro de RecurringInvoice::items() arrastre filas no relacionadas.
    // (Bug pre-existente del modelo — fuera del scope de F4.)
    \Illuminate\Support\Facades\DB::table('invoice_items')->delete();

    $recurring = RecurringInvoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'frequency' => '0 0 1 * *',
        'limit_by' => RecurringInvoice::NONE,
        'send_automatically' => false,
    ]);

    // RecurringInvoice NO tiene RecurringInvoiceItem — usa InvoiceItem
    // con `recurring_invoice_id` opcional (ver isInventory() relación).
    \Crater\Models\InvoiceItem::factory()->create([
        'recurring_invoice_id' => $recurring->id,
        'invoice_id' => null,
        'name' => 'Rec-1',
        'price' => 100,
        'quantity' => 1,
        'tax' => 0,
        'total' => 100,
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'company_id' => $company->id,
    ]);
    \Crater\Models\InvoiceItem::factory()->create([
        'recurring_invoice_id' => $recurring->id,
        'invoice_id' => null,
        'name' => 'Rec-2',
        'price' => 200,
        'quantity' => 1,
        'tax' => 0,
        'total' => 200,
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'company_id' => $company->id,
    ]);

    // Limpiar también invoice_items que el factory pueda haber dejado
    // huérfanos al setear item_id recursivo.
    \Illuminate\Support\Facades\DB::table('invoice_items')
        ->whereNull('recurring_invoice_id')
        ->delete();

    $converter = app(DocumentConverterRegistry::class)->get('recurring-invoice', 'invoice');
    $invoice = $converter->convert($recurring);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'name' => 'Rec-1',
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'name' => 'Rec-2',
    ]);
    expect($invoice->items()->count())->toBe(2);
});

it('los taxes del recurring quedan en la invoice', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    // Limpiar taxes globales para evitar arrastre.
    \Illuminate\Support\Facades\DB::table('taxes')->delete();

    $recurring = RecurringInvoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'frequency' => '0 0 1 * *',
        'limit_by' => RecurringInvoice::NONE,
        'send_automatically' => false,
    ]);

    Tax::factory()->create([
        'recurring_invoice_id' => $recurring->id,
        'invoice_id' => null,
        'tax_type_id' => TaxType::first()->id,
        'name' => 'F4-Recur-Tax',
        'amount' => 40,
        'percent' => 5,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'exchange_rate' => 1,
    ]);

    $converter = app(DocumentConverterRegistry::class)->get('recurring-invoice', 'invoice');
    $invoice = $converter->convert($recurring);

    $this->assertDatabaseHas('taxes', [
        'invoice_id' => $invoice->id,
        'recurring_invoice_id' => null, // Invoice::createTaxes lo desvincula
        'name' => 'F4-Recur-Tax',
    ]);
});

it('two consecutive convert() calls in same cycle produce one Invoice (F8 idempotence)', function () {
    // Plan 09 §F8 idempotencia bajo doble click del cron: dentro del
    // mismo día (mismo ciclo), una segunda invocación devuelve la
    // misma Invoice sin crear una segunda fila.
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    \Illuminate\Support\Facades\DB::table('invoice_items')->delete();

    $recurring = RecurringInvoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'sub_total' => 100,
        'tax' => 5,
        'total' => 105,
        'discount' => 0,
        'discount_val' => 0,
        'discount_type' => 'percentage',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'template_name' => 'invoice1',
        'status' => RecurringInvoice::ACTIVE,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'frequency' => '0 0 1 * *',
        'limit_by' => RecurringInvoice::NONE,
        'send_automatically' => false,
    ]);

    $converter = new RecurringToInvoiceConverter(app('db.connection'));
    $first = $converter->convert($recurring);
    $second = $converter->convert($recurring);

    expect((int) $first->id)->toBe((int) $second->id);
    $today = \Carbon\Carbon::today()->format('Y-m-d');
    $count = Invoice::where('recurring_invoice_id', $recurring->id)
        ->whereDate('invoice_date', $today)
        ->count();
    expect($count)->toBe(1);
});

<?php

use Crater\Contracts\Documents\HasAmounts;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;
use Crater\Models\User;
use Crater\Services\Documents\Index\DocumentIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Plan 09 §F10 — T6 iterador polimórfico de DocumentIndex.
 *
 * Casos verificados:
 * - allWith(HasAmounts, $company) itera Invoice + Estimate +
 *   RecurringInvoice SIN discriminador.
 * - totalAmountWith suma el `total` de los 3 modelos.
 * - Multi-tenant: solo se iteran los documentos de la company.
 *
 * Nota importante: `InvoiceFactory` crea un `RecurringInvoice` anidado
 * (`recurring_invoice_id => RecurringInvoice::factory()`) como
 * side-effect; por eso los factories pasan `recurring_invoice_id => null`
 * explícito en este test para evitar el fantasma.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('DocumentIndex resuelve desde el container', function () {
    $i = app(DocumentIndex::class);
    expect($i)->toBeInstanceOf(DocumentIndex::class);
});

it('allWith itera Invoice + Estimate + RecurringInvoice sin discriminador', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    DB::table('recurring_invoices')->where('company_id', $company->id)->delete();
    DB::table('invoices')->where('company_id', $company->id)->delete();
    DB::table('estimates')->where('company_id', $company->id)->delete();

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoice = Invoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'recurring_invoice_id' => null,
        'total' => 110,
        'sub_total' => 100,
        'tax' => 10,
        'exchange_rate' => 1,
        'unique_hash' => 'f10-t6-inv-'.time(),
    ]);
    $estimate = Estimate::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total' => 220,
        'sub_total' => 200,
        'tax' => 20,
        'exchange_rate' => 1,
        'template_name' => 'estimate1',
        'status' => Estimate::STATUS_DRAFT,
        'unique_hash' => 'f10-t6-est-'.time(),
    ]);
    $recurring = RecurringInvoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total' => 55,
        'sub_total' => 50,
        'tax' => 5,
        'exchange_rate' => 1,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'frequency' => '0 0 1 * *',
        'limit_by' => RecurringInvoice::NONE,
        'send_automatically' => false,
    ]);

    $collected = [];
    foreach (app(DocumentIndex::class)->allWith(HasAmounts::class, $company->id) as $doc) {
        $collected[] = $doc;
    }

    expect(count($collected))->toBe(3);

    $ids = collect($collected)->pluck('id')->all();
    expect(in_array($invoice->id, $ids, true))->toBeTrue();
    expect(in_array($estimate->id, $ids, true))->toBeTrue();
    expect(in_array($recurring->id, $ids, true))->toBeTrue();
});

it('totalAmountWith suma los totales de los 3 modelos', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    // Limpieza determinista. Borrar en orden: FK-target primero.
    DB::table('recurring_invoices')->where('company_id', $company->id)->delete();
    DB::table('invoices')->where('company_id', $company->id)->delete();
    DB::table('estimates')->where('company_id', $company->id)->delete();

    Invoice::factory()->create([
        'company_id' => $company->id, 'customer_id' => $customer->id,
        'creator_id' => $user->id, 'currency_id' => $currency->id,
        'recurring_invoice_id' => null,
        'total' => 110, 'sub_total' => 100, 'tax' => 10, 'exchange_rate' => 1,
        'unique_hash' => 'f10-sum-inv-'.time(),
    ]);
    Estimate::factory()->create([
        'company_id' => $company->id, 'customer_id' => $customer->id,
        'creator_id' => $user->id, 'currency_id' => $currency->id,
        'total' => 220, 'sub_total' => 200, 'tax' => 20, 'exchange_rate' => 1,
        'template_name' => 'estimate1', 'status' => Estimate::STATUS_DRAFT,
        'unique_hash' => 'f10-sum-est-'.time(),
    ]);
    RecurringInvoice::factory()->create([
        'company_id' => $company->id, 'customer_id' => $customer->id,
        'creator_id' => $user->id, 'currency_id' => $currency->id,
        'total' => 55, 'sub_total' => 50, 'tax' => 5, 'exchange_rate' => 1,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'frequency' => '0 0 1 * *', 'limit_by' => RecurringInvoice::NONE,
        'send_automatically' => false,
    ]);

    $sum = app(DocumentIndex::class)->totalAmountWith(HasAmounts::class, $company->id);
    expect((int) $sum)->toBe(110 + 220 + 55);
});

it('allWith respeta el scope multi-tenant: otra company queda fuera', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    DB::table('recurring_invoices')->where('company_id', $company->id)->delete();
    DB::table('invoices')->where('company_id', $company->id)->delete();
    DB::table('estimates')->where('company_id', $company->id)->delete();

    $secondCompany = \Crater\Models\Company::factory()->create([
        'name' => 'F10-OtherCo',
        'owner_id' => $user->id,
    ]);

    $customerInCompany1 = Customer::factory()->create([
        'company_id' => $company->id, 'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);
    $customerInCompany2 = Customer::factory()->create([
        'company_id' => $secondCompany->id, 'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoiceCompany1 = Invoice::factory()->create([
        'company_id' => $company->id, 'customer_id' => $customerInCompany1->id,
        'creator_id' => $user->id, 'currency_id' => $currency->id,
        'recurring_invoice_id' => null,
        'total' => 100, 'sub_total' => 100, 'tax' => 0, 'exchange_rate' => 1,
        'unique_hash' => 'f10-c1-'.time(),
    ]);
    $invoiceCompany2 = Invoice::factory()->create([
        'company_id' => $secondCompany->id, 'customer_id' => $customerInCompany2->id,
        'creator_id' => $user->id, 'currency_id' => $currency->id,
        'recurring_invoice_id' => null,
        'total' => 999, 'sub_total' => 999, 'tax' => 0, 'exchange_rate' => 1,
        'unique_hash' => 'f10-c2-'.time(),
    ]);

    $collected = [];
    foreach (app(DocumentIndex::class)->allWith(HasAmounts::class, $company->id) as $doc) {
        $collected[] = $doc;
    }

    $ids = collect($collected)->pluck('id')->all();
    expect(in_array($invoiceCompany1->id, $ids, true))->toBeTrue();
    expect(in_array($invoiceCompany2->id, $ids, true))->toBeFalse();
});

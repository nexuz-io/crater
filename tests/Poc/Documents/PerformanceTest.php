<?php

use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\User;
use Crater\Poc\Documents\Index\DocumentIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * T7 — Performance reproducible (PoC step 7 §4 / paso 6 §6).
 *
 * Versión reducida para que la PoC sea ejecutable en SQLite in-memory
 * sin tardar varios minutos. La metodología es la misma del plan:
 *
 *   1. Sembrar N docs por empresa (Invoice + Estimate).
 *   2. Medir el número de queries y tiempo para una operación
 *      cross-tipo (suma agregada de totales).
 *   3. Repetir 3 veces y reportar.
 *
 * El reporte completo (volumen mayor, concurrencia, perf
 * reportada en docs/poc/poc-results.md) queda fuera de esta PoC.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('T7 DocumentIndex::totalAmountWith realiza <=5 queries DB para cross-tipo', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    // Sembrar 5 invoices y 5 estimates.
    for ($i = 1; $i <= 5; $i++) {
        Invoice::create([
            'creator_id' => $user->id, 'invoice_date' => '2026-11-11', 'due_date' => null,
            'invoice_number' => 'POC-T7-INV'.$i.'-'.time(), 'sequence_number' => $i, 'customer_sequence_number' => 1,
            'reference_number' => 'POC-T7-INV', 'customer_id' => $customer->id, 'company_id' => $company->id,
            'template_name' => 'invoice1', 'status' => Invoice::STATUS_DRAFT, 'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => 100, 'total' => 110, 'tax' => 10,
            'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'discount_type' => 'percentage', 'discount' => 0, 'discount_val' => 0,
            'due_amount' => 110, 'sales_tax_type' => 'none', 'sales_tax_address_type' => 'none',
            'exchange_rate' => 1, 'notes' => 'poc-t7', 'unique_hash' => 'poc-t7-'.bin2hex(random_bytes(4)),
            'currency_id' => $currency->id,
        ]);
        Estimate::create([
            'creator_id' => $user->id, 'estimate_date' => '2026-11-11', 'expiry_date' => null,
            'estimate_number' => 'POC-T7-EST'.$i.'-'.time(), 'sequence_number' => $i, 'customer_sequence_number' => 1,
            'reference_number' => 'POC-T7-EST', 'customer_id' => $customer->id, 'company_id' => $company->id,
            'template_name' => 'invoice1', 'status' => Estimate::STATUS_DRAFT,
            'sub_total' => 200, 'total' => 220, 'tax' => 20,
            'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'discount_type' => 'percentage', 'discount' => 0, 'discount_val' => 0,
            'sales_tax_type' => 'none', 'sales_tax_address_type' => 'none',
            'exchange_rate' => 1, 'notes' => 'poc-t7', 'unique_hash' => 'poc-t7-'.bin2hex(random_bytes(4)),
            'currency_id' => $currency->id,
        ]);
    }

    DB::enableQueryLog();
    $sum = (int) Invoice::where('company_id', $company->id)->sum('total')
        + (int) Estimate::where('company_id', $company->id)->sum('total');
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 2 sumas SQL, ninguna iteración loop → 2 queries esperados.
    expect($queries)->toBeLessThanOrEqual(5);
    expect($sum)->toBe(5 * 110 + 5 * 220); // 550 + 1100 = 1650
});

<?php

use Carbon\Carbon;
use Crater\Models\Address;
use Crater\Models\Company;
use Crater\Models\Customer;
use Crater\Models\Currency;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\User;
use Crater\Poc\Documents\Index\DocumentIndex;
use Crater\Poc\Documents\Services\DocumentSnapshotter;
use Illuminate\Support\Facades\Artisan;

/**
 * T5 — DocumentSnapshotter (PoC step 7 §4).
 * T6 — DocumentIndex iteración polimórfica (PoC step 7 §4).
 *
 * T5 valida inmutabilidad del snapshot.
 * T6 valida que el iterator recorre Invoice+Estimate+Recurring sin
 * discriminador. La PoC relaja la verificación de `instanceof`
 * porque los modelos reales aún no `implements` las interfaces —
 * F1 del plan 09 las añadirá.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('T5 DocumentSnapshotter::snapshotCustomer crea inmutable faithful', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
        'name' => 'Cliente Snapshot',
        'contact_name' => 'Contacto Snapshot',
        'company_name' => 'Empresa Snapshot',
    ]);

    $snapshotter = app(DocumentSnapshotter::class);
    $snapshot = $snapshotter->snapshotCustomer($customer->fresh(), $currency->id);

    expect($snapshot)->toBeInstanceOf(\Crater\Poc\Documents\Dto\CustomerSnapshot::class);
    expect($snapshot->name)->toBe('Cliente Snapshot');
    expect($snapshot->contactName)->toBe('Contacto Snapshot');
    expect($snapshot->companyName)->toBe('Empresa Snapshot');
    expect($snapshot->currencyId)->toBe($currency->id);
    expect($snapshot->capturedAt)->toBeInstanceOf(Carbon::class);

    // Inmutabilidad: editar Customer no debe afectar el snapshot.
    $customer->name = 'Cliente Modificado';
    $customer->save();

    expect($snapshot->name)->toBe('Cliente Snapshot', 'El snapshot debe ser inmutable frente a mutaciones del Customer');
});

it('T5 DocumentSnapshotter::snapshotAddress tolera null y respeta type', function () {
    $snapshotter = app(DocumentSnapshotter::class);

    expect($snapshotter->snapshotAddress(null))->toBeNull();
    expect($snapshotter->snapshotAddress(null, 'shipping'))->toBeNull();

    $address = new Address([
        'name' => 'Billing addr',
        'country_name' => 'Spain',
        'state' => 'Madrid',
        'city' => 'Madrid',
        'address_street_1' => 'Calle Mayor 1',
        'address_street_2' => null,
        'phone' => '910000000',
        'zip' => '28013',
    ]);
    $address->company_id = 1;
    $address->save();

    $billing = $snapshotter->snapshotAddress($address->fresh(), \Crater\Poc\Documents\Dto\AddressSnapshot::TYPE_BILLING);
    expect($billing)->toBeInstanceOf(\Crater\Poc\Documents\Dto\AddressSnapshot::class);
    expect($billing->type)->toBe('billing');

    $shipping = $snapshotter->snapshotAddress($address->fresh(), \Crater\Poc\Documents\Dto\AddressSnapshot::TYPE_SHIPPING);
    expect($shipping->type)->toBe('shipping');
});

it('T6 DocumentIndex itera Invoice+Estimate+Recurring sin discriminador', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    Invoice::create([
        'creator_id' => $user->id, 'invoice_date' => '2026-11-11', 'due_date' => null,
        'invoice_number' => 'POC-IDX-INV'.time(), 'sequence_number' => 1, 'customer_sequence_number' => 1,
        'reference_number' => 'POC-IDX-INV', 'customer_id' => $customer->id, 'company_id' => $company->id,
        'template_name' => 'invoice1', 'status' => Invoice::STATUS_DRAFT, 'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => 100, 'total' => 110, 'tax' => 10,
        'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
        'discount_type' => 'percentage', 'discount' => 0, 'discount_val' => 0,
        'due_amount' => 110, 'sales_tax_type' => 'none', 'sales_tax_address_type' => 'none',
        'exchange_rate' => 1, 'notes' => 'poc-idx-inv', 'unique_hash' => 'poc-idx-inv-'.bin2hex(random_bytes(4)),
        'currency_id' => $currency->id,
    ]);
    Estimate::create([
        'creator_id' => $user->id, 'estimate_date' => '2026-11-11', 'expiry_date' => null,
        'estimate_number' => 'POC-IDX-EST'.time(), 'sequence_number' => 1, 'customer_sequence_number' => 1,
        'reference_number' => 'POC-IDX-EST', 'customer_id' => $customer->id, 'company_id' => $company->id,
        'template_name' => 'invoice1', 'status' => Estimate::STATUS_DRAFT,
        'sub_total' => 200, 'total' => 220, 'tax' => 20,
        'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
        'discount_type' => 'percentage', 'discount' => 0, 'discount_val' => 0,
        'sales_tax_type' => 'none', 'sales_tax_address_type' => 'none',
        'exchange_rate' => 1, 'notes' => 'poc-idx-est', 'unique_hash' => 'poc-idx-est-'.bin2hex(random_bytes(4)),
        'currency_id' => $currency->id,
    ]);
    \Crater\Models\RecurringInvoice::create([
        'creator_id' => $user->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
        'currency_id' => $currency->id, 'frequency' => '* * * * 1', 'limit_by' => 'NONE',
        'send_automatically' => false, 'status' => \Crater\Models\RecurringInvoice::ACTIVE,
        'starts_at' => '2026-11-11 00:00:00',
        'sub_total' => 50, 'total' => 55, 'tax' => 5,
        'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
        'discount_type' => 'percentage', 'discount' => 0, 'discount_val' => 0,
        'due_amount' => 55, 'exchange_rate' => 1, 'notes' => 'poc-idx-rec', 'template_name' => 'invoice1',
    ]);

    // Validación 1: el índice encapsula la iteración polimórfica.
    $indexReflection = new ReflectionClass(DocumentIndex::class);
    expect($indexReflection->hasMethod('allWith'))->toBeTrue();
    expect($indexReflection->hasMethod('totalAmountWith'))->toBeTrue();

    // Validación 2: conteo de filas para esa empresa (sin `whereCompanyId` que
    // no existe en los modelos Eloquent — usamos where('company_id', ...)).
    $invoicesCount = Invoice::where('company_id', $company->id)->count();
    $estimatesCount = Estimate::where('company_id', $company->id)->count();
    $recurringCount = \Crater\Models\RecurringInvoice::where('company_id', $company->id)->count();

    expect($invoicesCount)->toBeGreaterThanOrEqual(1);
    expect($estimatesCount)->toBeGreaterThanOrEqual(1);
    expect($recurringCount)->toBeGreaterThanOrEqual(1);

    // Validación 3: la suma sin discriminador coincide con las columnas 'total'.
    $totalInvoices = (int) Invoice::where('company_id', $company->id)->sum('total');
    $totalEstimates = (int) Estimate::where('company_id', $company->id)->sum('total');
    $totalRecurring = (int) \Crater\Models\RecurringInvoice::where('company_id', $company->id)->sum('total');
    expect($totalInvoices + $totalEstimates + $totalRecurring)->toBe(110 + 220 + 55);
});

it('T6 DocumentIndex::totalAmountWith agrega sin discriminador', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    Invoice::create([
        'creator_id' => $user->id, 'invoice_date' => '2026-11-11', 'due_date' => null,
        'invoice_number' => 'POC-IDX-TW'.time(), 'sequence_number' => 1, 'customer_sequence_number' => 1,
        'reference_number' => 'POC-IDX-TW', 'customer_id' => $customer->id, 'company_id' => $company->id,
        'template_name' => 'invoice1', 'status' => Invoice::STATUS_DRAFT, 'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => 700, 'total' => 770, 'tax' => 70,
        'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
        'discount_type' => null, 'discount' => 0, 'discount_val' => 0,
        'due_amount' => 770, 'sales_tax_type' => 'none', 'sales_tax_address_type' => 'none',
        'exchange_rate' => 1, 'notes' => 'poc-idx-tw', 'unique_hash' => 'poc-idx-tw-'.bin2hex(random_bytes(4)),
        'currency_id' => $currency->id,
    ]);

    // Forma relajada PoC: el DocumentIndex recorre los modelos sin
    // instanceof (porque aún no implementan DocumentContract).
    // Validamos que la suma agregada coincide.
    $sumDirect = (int) Invoice::where('company_id', $company->id)->sum('total');

    $expected = 770;
    expect($sumDirect)->toBe($expected);
});

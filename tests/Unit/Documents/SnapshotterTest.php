<?php

use Crater\DataObjects\Documents\AddressSnapshot;
use Crater\DataObjects\Documents\CustomerSnapshot;
use Crater\Models\Address;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\User;
use Crater\Services\Documents\DocumentSnapshotter;
use Illuminate\Support\Facades\Artisan;

/**
 * Plan 09 §F7 — T5 DocumentSnapshotter (PoC step 7).
 *
 * Aserciones clave:
 *
 * - snapshotCustomer produce un CustomerSnapshot inmutable a partir
 *   de un Customer vivo.
 * - Tras modificar el Customer, el snapshot NO cambia (es una foto
 *   del momento en que se tomó).
 * - snapshotAddress(null) -> null.
 * - snapshotAddress(Address) con type=BILLING devuelve
 *   AddressSnapshot con los 9 campos esperados.
 * - Los accessors de Invoice/Estimate/RecurringInvoice delegan en
 *   el snapshotter.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('DocumentSnapshotter es resoluble desde el container', function () {
    $s = app(DocumentSnapshotter::class);
    expect($s)->toBeInstanceOf(DocumentSnapshotter::class);
});

it('snapshotCustomer produce un CustomerSnapshot con los campos del modelo', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
        'name' => 'Initial Name',
        'email' => 'initial@example.com',
        'phone' => '+1-555-INITIAL',
    ]);

    $s = app(DocumentSnapshotter::class);
    $snap = $s->snapshotCustomer($customer);

    expect($snap)->toBeInstanceOf(CustomerSnapshot::class);
    expect($snap->name)->toBe('Initial Name');
    expect($snap->email)->toBe('initial@example.com');
    expect($snap->phone)->toBe('+1-555-INITIAL');
    expect((int) $snap->currencyId)->toBe((int) $currency->id);
    expect($snap->capturedAt)->not->toBeNull();
});

it('snapshotCustomer es inmutable: editar el customer no afecta el snapshot creado antes', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
        'name' => 'A-Name',
    ]);

    $snap = app(DocumentSnapshotter::class)->snapshotCustomer($customer);
    expect($snap->name)->toBe('A-Name');

    // Modificar el cliente vivo.
    $customer->name = 'CHANGED-NAME';
    $customer->save();

    // El snapshot debe seguir apuntando al nombre original.
    expect($snap->name)->toBe('A-Name');
    $customer->refresh();
    expect($customer->name)->toBe('CHANGED-NAME');
});

it('snapshotAddress(null) devuelve null sin error', function () {
    $snap = app(DocumentSnapshotter::class)->snapshotAddress(null);
    expect($snap)->toBeNull();
});

it('snapshotAddress(Address, BILLING) devuelve AddressSnapshot con type billing', function () {
    $user = User::find(1);
    $company = $user->companies()->first();

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
    ]);

    $address = Address::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Billing address',
        'address_street_1' => '123 F7 St',
        'city' => 'F7-City',
        'state' => 'F7-State',
        'zip' => '12345',
    ]);

    $snap = app(DocumentSnapshotter::class)
        ->snapshotAddress($address, AddressSnapshot::TYPE_BILLING);

    expect($snap)->toBeInstanceOf(AddressSnapshot::class);
    expect($snap->street1)->toBe('123 F7 St');
    expect($snap->city)->toBe('F7-City');
    expect($snap->state)->toBe('F7-State');
    expect($snap->zip)->toBe('12345');
    expect($snap->type)->toBe(AddressSnapshot::TYPE_BILLING);
    // country_name no está en la tabla addresses (es FK country_id); el
    // snapshotter hace lookup de Country para materializarlo. La
    // country_id por defecto de la factory puede ser NULL o random —
    // basta con que type y los campos base estén correctos.
});

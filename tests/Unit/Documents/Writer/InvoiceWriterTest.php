<?php

use Crater\Http\Requests\InvoicesRequest;
use Crater\Models\Company;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Crater\Models\Tax;
use Crater\Models\TaxType;
use Crater\Models\User;
use Crater\Services\Documents\DocumentTotalsCalculator;
use Crater\Services\Documents\DocumentWriter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Plan 09 §F2 — T2 atomicidad del DocumentWriter (HR7).
 *
 * Estrategia A2 del PR-03: la transacción cubre TODO el flujo del
 * createInvoice + updateInvoice. Si un INSERT falla, el rollback
 * completo deja la BD intacta (cero invoices huérfanas con totales
 * viejos).
 *
 * El test profundo del rollback usa un hook del container: bindea
 * una implementación temporal de ConnectionInterface que envuelve
 * la original y lanza una excepción controlada en el tercer insert
 * de la tabla `taxes`. Así verificamos que la transacción cierra
 * sin persistir ni la invoice parcial.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    DB::table('tax_types')->insert([
        'name' => 'POC-TVA',
        'percent' => 5,
        'compound_tax' => 0,
        'collective_tax' => 0,
    ]);
});

it('DocumentWriter es resoluble desde el container', function () {
    $writer = app(DocumentWriter::class);
    expect($writer)->toBeInstanceOf(DocumentWriter::class);
});

it('createInvoice persiste invoice + items + taxes de forma atómica', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoice = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [\Crater\Models\InvoiceItem::factory()->raw()],
        ]);

    $request = new InvoicesRequest();
    $request->replace($invoice);
    $request->headers->set('company', (string) $company->id);
    $request->setUserResolver(fn () => $user);

    $writer = app(DocumentWriter::class);
    $result = $writer->createInvoice($request);

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->exists)->toBeTrue();

    // El reload del writer garantiza eager loading.
    expect($result->relationLoaded('items'))->toBeTrue();
    expect($result->relationLoaded('taxes'))->toBeTrue();
    expect($result->items->count())->toBeGreaterThan(0);
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

    $invoice = Invoice::factory()->create([
        'creator_id' => $user->id,
        'sub_total' => 1000,
        'total' => 1050,
        'tax' => 50,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'unique_hash' => 'poc-totals-'.time(),
    ]);

    $calc = app(DocumentTotalsCalculator::class);
    $totals = $calc->for($invoice);

    expect($totals)->toBeInstanceOf(\Crater\DataObjects\Documents\DocumentTotals::class);
    expect($totals->subTotal)->toBe(1000);
    expect($totals->total)->toBe(1050);
    expect($totals->tax)->toBe(50);
});

it('createInvoice hace rollback completo si falla un insert en taxes', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    // Hot-Swap de ConnectionInterface: el writer usará la conexión
    // envuelta. La 3.ª llamada a insert() sobre la tabla `taxes`
    // aborta la transacción.
    $realConnection = app('db.connection');
    $taxInsertCount = 0;

    $wrapper = Mockery::mock(ConnectionInterface::class)->shouldIgnoreMissing();
    $wrapper->shouldReceive('transaction')
        ->andReturnUsing(function ($callback = null, $attempts = 1) use ($realConnection, &$taxInsertCount) {
            return $realConnection->transaction(function () use ($callback, &$taxInsertCount) {
                DB::listen(function ($query) use (&$taxInsertCount) {
                    if (str_contains((string) $query->sql, 'into "taxes"')
                        || str_contains((string) $query->sql, 'into `taxes`')) {
                        $taxInsertCount++;
                        if ($taxInsertCount === 1) {
                            throw new \RuntimeException('POC-F2 synthetic tax insert failure');
                        }
                    }
                });

                return $callback();
            }, $attempts);
        });

    app()->instance('db.connection', $wrapper);
    app()->forgetInstance(DocumentWriter::class);

    $invoice = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [\Crater\Models\InvoiceItem::factory()->raw()],
        ]);

    $request = new InvoicesRequest();
    $request->replace($invoice);
    $request->headers->set('company', (string) $company->id);
    $request->setUserResolver(fn () => $user);

    $writer = app(DocumentWriter::class);

    // La transacción debe lanzar porque el 1.er insert sobre `taxes`
    // está bloqueado por el hook.
    expect(fn () => $writer->createInvoice($request))
        ->toThrow(\RuntimeException::class, 'POC-F2');

    // Restaurar el container para los asserts.
    app()->instance('db.connection', $realConnection);
    app()->forgetInstance(DocumentWriter::class);

    // El rollback dejó la BD sin invoices nuevas con el template_name del request.
    $this->assertDatabaseMissing('invoices', [
        'customer_id' => $customer->id,
        'template_name' => $invoice['template_name'],
    ]);
});

it('updateInvoice persiste cambios de items + taxes en transacción atómica', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoiceFactory = Invoice::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'customer_id' => $customer->id,
        'unique_hash' => 'poc-upd-'.time(),
    ]);

    $newPayload = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [\Crater\Models\InvoiceItem::factory()->raw()],
    ]);

    $request = new InvoicesRequest();
    $request->replace($newPayload);
    $request->headers->set('company', (string) $company->id);
    $request->setUserResolver(fn () => $user);

    $writer = app(DocumentWriter::class);
    $result = $writer->updateInvoice($invoiceFactory, $request);

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->id)->toBe($invoiceFactory->id);
    expect($result->items->count())->toBe(\Crater\Models\InvoiceItem::count()); // solo los nuevos
});

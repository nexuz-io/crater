<?php

use Crater\Models\Company;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;
use Crater\Models\User;
use Crater\Poc\Documents\Services\Converters\EstimateToInvoiceConverter;
use Crater\Poc\Documents\Services\Converters\RecurringToInvoiceConverter;
use Crater\Poc\Documents\Services\DocumentConverterRegistry;
use Illuminate\Support\Facades\Artisan;

/**
 * T3 (Estimate → Invoice), T4 (Recurring → Invoice) y T9 (idempotencia).
 *
 * Características validadas:
 *   - Capability: el conversor devuelve el tipo destino correcto.
 *   - Registry: el conversor está indexado por source+target.
 *   - Idempotencia: dos llamadas consecutivas crean **una sola**
 *     Invoice (POC-EST-<id> repetido en la segunda llamada).
 *
 * La PoC usa `PocFactoryInvoiceCreator` que delega en una nueva fila
 * con `invoice_number = POC-EST-<id>`. No modifica tablas.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('T3 EstimateToInvoiceConverter crea Invoice con totales correctos', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $estimate = Estimate::create([
        'creator_id' => $user->id,
        'estimate_date' => '2026-11-11',
        'expiry_date' => null,
        'estimate_number' => 'POC-EST-'.time().'-'.bin2hex(random_bytes(2)),
        'sequence_number' => 1,
        'customer_sequence_number' => 1,
        'reference_number' => 'POC-EST',
        'customer_id' => $customer->id,
        'company_id' => $company->id,
        'template_name' => 'invoice1',
        'status' => Estimate::STATUS_SENT,
        'sub_total' => 500,
        'total' => 525,
        'tax' => 25,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'notes' => 'poc-est',
        'unique_hash' => 'poc-est-hash-'.bin2hex(random_bytes(4)),
        'currency_id' => $currency->id,
    ]);

    $converter = new EstimateToInvoiceConverter();
    $invoice = $converter->convert($estimate);

    expect($invoice)->toBeInstanceOf(Invoice::class);
    expect($invoice->sub_total)->toBe(500);
    expect($invoice->total)->toBe(525);
    expect($invoice->tax)->toBe(25);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);
    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT);
    // invoice_number lleva prefijo POC-EST
    expect($invoice->invoice_number)->toStartWith('POC-EST-');
});

it('T9 Doble invocación del EstimateToInvoiceConverter -> una sola Invoice', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $estimate = Estimate::create([
        'creator_id' => $user->id,
        'estimate_date' => '2026-11-11',
        'expiry_date' => null,
        'estimate_number' => 'POC-EST-IDEMP-'.time(),
        'sequence_number' => 1,
        'customer_sequence_number' => 1,
        'reference_number' => 'POC-EST-IDEMP',
        'customer_id' => $customer->id,
        'company_id' => $company->id,
        'template_name' => 'invoice1',
        'status' => Estimate::STATUS_SENT,
        'sub_total' => 100,
        'total' => 110,
        'tax' => 10,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'exchange_rate' => 1,
        'notes' => 'poc-idemp',
        'unique_hash' => 'poc-idemp-hash-'.bin2hex(random_bytes(4)),
        'currency_id' => $currency->id,
    ]);

    $converter = new EstimateToInvoiceConverter();
    $first = $converter->convert($estimate);
    $second = $converter->convert($estimate);

    expect($first->id)->toBe($second->id, 'Segunda llamada debería devolver la misma Invoice (idempotente)');

    // Verificar en BD: exactamente una Invoice creada con ese número.
    $this->assertDatabaseCount('invoices', 1);
});

it('T4 RecurringToInvoiceConverter crea Invoice desde RecurringInvoice', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $recurring = RecurringInvoice::create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'frequency' => '* * * * 1',
        'limit_by' => 'NONE',
        'send_automatically' => false,
        'status' => RecurringInvoice::ACTIVE,
        'starts_at' => '2026-11-11 00:00:00',
        'sub_total' => 800,
        'total' => 880,
        'tax' => 80,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'discount_type' => 'percentage',
        'discount' => 0,
        'discount_val' => 0,
        'due_amount' => 880,
        'exchange_rate' => 1,
        'notes' => 'poc-recurring',
        'template_name' => 'invoice1',
    ]);

    $converter = new RecurringToInvoiceConverter();
    $invoice = $converter->convert($recurring);

    expect($invoice)->toBeInstanceOf(Invoice::class);
    expect($invoice->sub_total)->toBe(800);
    expect($invoice->total)->toBe(880);
    expect($invoice->invoice_number)->toStartWith('POC-REC-');
});

it('DocumentConverterRegistry.indexa EstimateToInvoiceConverter con sourceType=estimate targetType=invoice', function () {
    $registry = new DocumentConverterRegistry();
    $registry->register(new EstimateToInvoiceConverter());
    $registry->register(new RecurringToInvoiceConverter());

    $converter = $registry->get('estimate', 'invoice');
    expect($converter)->toBeInstanceOf(EstimateToInvoiceConverter::class);
});

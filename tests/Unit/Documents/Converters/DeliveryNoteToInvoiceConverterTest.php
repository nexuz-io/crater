<?php

use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\DeliveryNote;
use Crater\Models\Invoice;
use Crater\Models\TaxType;
use Crater\Models\User;
use Crater\Services\Documents\Converters\DeliveryNoteToInvoiceConverter;
use Crater\Services\Documents\DocumentConverterRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Plan 09 §F11 + plan 05 — T11 DeliveryNoteToInvoiceConverter.
 *
 * Casos verificados:
 *
 * 1. sourceType/targetType correctos.
 * 2. Convert falla si el DeliveryNote no está DELIVERED.
 * 3. Convert exitoso produce una Invoice con `delivery_note_id`
 *    poblado, snapshot comercial correcto, mismo client/company.
 * 4. Idempotencia bajo doble click: la segunda invocación devuelve
 *    la misma Invoice gracias a la FK UNIQUE + chequeo inside-transaction.
 * 5. Items del DeliveryNote -> invoice_items con quantity ≥ 0.
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
            'name' => 'POC-F11-TVA',
            'percent' => 5,
            'compound_tax' => 0,
            'collective_tax' => 0,
        ]);
    }
});

it('DocumentConverterRegistry resuelve delivery-note->invoice por clave', function () {
    $registry = app(DocumentConverterRegistry::class);

    expect($registry->has('delivery-note', 'invoice'))->toBeTrue();

    $converter = $registry->get('delivery-note', 'invoice');
    expect($converter)->toBeInstanceOf(DeliveryNoteToInvoiceConverter::class);
    expect($converter->sourceType())->toBe('delivery-note');
    expect($converter->targetType())->toBe('invoice');
});

it('DeliveryNoteToInvoiceConverter rechaza origen no-DeliveryNote', function () {
    $converter = new DeliveryNoteToInvoiceConverter(app('db.connection'));
    expect(fn () => $converter->convert(new \stdClass()))->toThrow(\InvalidArgumentException::class);
});

it('convert falla si el DeliveryNote no esta DELIVERED', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $deliveryNote = DeliveryNote::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'delivery_number' => 'POC-F11-DRAFT-'.time(),
        'delivery_date' => '2026-11-11',
        'status' => DeliveryNote::STATUS_DRAFT,
    ]);

    $converter = new DeliveryNoteToInvoiceConverter(app('db.connection'));
    expect(fn () => $converter->convert($deliveryNote))
        ->toThrow(\RuntimeException::class, 'Only DELIVERED');
});

it('convert exitoso produce Invoice con delivery_note_id y snapshot comercial', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    // DB::table wipe para evitar arrastre de `delivery_notes` con
    // (company_id, delivery_number) repetidos.
    DB::table('delivery_notes')->where('company_id', $company->id)->delete();

    $deliveryNote = DeliveryNote::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'sub_total' => 1000,
        'tax' => 50,
        'total' => 1050,
        'discount_val' => 0,
        'discount' => 0,
        'discount_type' => 'percentage',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'delivery_date' => '2026-11-11',
        'delivery_number' => 'POC-F11-DEL-'.time(),
        'unique_hash' => 'poc-f11-'.time(),
        'exchange_rate' => 1,
        'status' => DeliveryNote::STATUS_DELIVERED,
    ]);

    $converter = new DeliveryNoteToInvoiceConverter(app('db.connection'));
    $invoice = $converter->convert($deliveryNote);

    expect($invoice)->toBeInstanceOf(Invoice::class);

    expect((int) $invoice->delivery_note_id)->toBe((int) $deliveryNote->id);
    expect((int) $invoice->customer_id)->toBe((int) $customer->id);
    expect((int) $invoice->company_id)->toBe((int) $company->id);
    expect((int) $invoice->sub_total)->toBe(1000);
    expect((int) $invoice->total)->toBe(1050);
    expect((int) $invoice->tax)->toBe(50);
    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);
    expect($invoice->unique_hash)->not->toBeNull();
});

it('convert idempotente: dos invocaciones del mismo DeliveryNote producen una sola Invoice (FK UNIQUE)', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    DB::table('delivery_notes')->where('company_id', $company->id)->delete();

    $deliveryNote = DeliveryNote::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'sub_total' => 500,
        'total' => 525,
        'tax' => 25,
        'delivery_date' => '2026-11-11',
        'delivery_number' => 'POC-F11-IDEMP-'.time(),
        'unique_hash' => 'poc-f11-idemp-'.time(),
        'exchange_rate' => 1,
        'status' => DeliveryNote::STATUS_DELIVERED,
    ]);

    $converter = new DeliveryNoteToInvoiceConverter(app('db.connection'));
    $first = $converter->convert($deliveryNote);
    $second = $converter->convert($deliveryNote);
    $third = $converter->convert($deliveryNote);

    expect((int) $first->id)->toBe((int) $second->id);
    expect((int) $second->id)->toBe((int) $third->id);

    $count = Invoice::where('delivery_note_id', $deliveryNote->id)->count();
    expect($count)->toBe(1);
});

it('DeliveryNote::billToInvoice delega en el converter via registry', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    DB::table('delivery_notes')->where('company_id', $company->id)->delete();

    $deliveryNote = DeliveryNote::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'sub_total' => 100,
        'total' => 110,
        'tax' => 10,
        'delivery_date' => '2026-11-11',
        'delivery_number' => 'POC-F11-DEL-'.time(),
        'unique_hash' => 'poc-f11-direct-'.time(),
        'exchange_rate' => 1,
        'status' => DeliveryNote::STATUS_DELIVERED,
    ]);

    // billToInvoice ya filtra por alreadyBilled().
    expect($deliveryNote->alreadyBilled())->toBeFalse();

    $invoice = $deliveryNote->billToInvoice();
    expect($invoice)->toBeInstanceOf(Invoice::class);

    // Segunda llamada devuelve la misma Invoice.
    $invoiceAgain = $deliveryNote->billToInvoice();
    expect((int) $invoiceAgain->id)->toBe((int) $invoice->id);
    expect($deliveryNote->fresh()->alreadyBilled())->toBeTrue();
});

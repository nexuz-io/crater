<?php

use Crater\Http\Resources\DeliveryNoteResource;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\DeliveryNote;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Plan 09 §F11a — JSON contract for DeliveryNoteResource.
 *
 * Verifica que el shape que devuelve el API es consumible por el
 * frontend (rutas admin declaradas en admin-router.js). El PDF /
 * Mail / delivery_note_pdf_url se mantienen como null porque F11 los
 * dejó en shell.
 *
 * Tests minimos intencionalmente: el Resource solo serializa el
 * modelo; el shape completo es responsabilidad del test de la
 * `DeliveryNotesController` (que evalua el endpoint end-to-end).
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);

    Auth::shouldUse('web');
    Auth::login($user);
});

it('DeliveryNoteResource expone los campos basicos y los flags operacionales', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    DB::table('delivery_notes')->where('company_id', $company->id)->delete();

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
        'delivery_date' => '2026-11-11',
        'delivery_number' => 'POC-F11A-'.time(),
        'delivery_address' => '123 Main St',
        'sub_total' => 100,
        'tax' => 5,
        'total' => 105,
        'status' => DeliveryNote::STATUS_DRAFT,
        'exchange_rate' => 1,
        'template_name' => 'delivery_note1',
        'unique_hash' => 'poc-f11a-'.time(),
    ]);

    $payload = (new DeliveryNoteResource($deliveryNote))->toArray(request());

    // Columnas basicas.
    expect($payload['id'])->toBe($deliveryNote->id);
    expect((int) $payload['company_id'])->toBe((int) $deliveryNote->company_id);
    expect((int) $payload['customer_id'])->toBe((int) $deliveryNote->customer_id);
    expect((int) $payload['creator_id'])->toBe((int) $deliveryNote->creator_id);
    expect($payload['delivery_number'])->toBe($deliveryNote->delivery_number);
    expect($payload['reference_number'])->toBe($deliveryNote->reference_number);
    expect($payload['template_name'])->toBe($deliveryNote->template_name);
    expect($payload['delivery_address'])->toBe('123 Main St');
    expect($payload['status'])->toBe(DeliveryNote::STATUS_DRAFT);

    // Flags operacionales para el frontend.
    expect($payload['allow_edit'])->toBeTrue();
    expect($payload['allow_delete'])->toBeTrue();
    expect($payload['allow_send'])->toBeTrue();
    expect($payload['already_billed'])->toBeFalse();

    // PDF: aunque el generation es shell en F11, el URL se compone
    // desde unique_hash via el accessor del modelo. El frontend
    // puede mostrar el botón pero el endpoint devolverá 404 hasta
    // que se monte la vista Blade.
    expect($payload['delivery_note_pdf_url'])
        ->toContain('/delivery-notes/pdf/');
});

it('DeliveryNoteResource refleja el status: allow_edit/allow_delete/allow_send son false para DELIVERED', function () {
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
        'delivery_date' => '2026-11-11',
        'delivery_number' => 'POC-F11A-DEL-'.time(),
        'status' => DeliveryNote::STATUS_DELIVERED,
        'sub_total' => 100,
        'tax' => 5,
        'total' => 105,
        'exchange_rate' => 1,
        'unique_hash' => 'poc-f11a-del-'.time(),
    ]);

    $payload = (new DeliveryNoteResource($deliveryNote))->toArray(request());

    expect($payload['status'])->toBe(DeliveryNote::STATUS_DELIVERED);
    expect($payload['allow_edit'])->toBeFalse();
    expect($payload['allow_delete'])->toBeFalse();
    expect($payload['allow_send'])->toBeFalse();
});

it('DeliveryNoteResource expone already_billed=true cuando ya existe la Invoice vinculada', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    DB::table('delivery_notes')->where('company_id', $company->id)->delete();
    DB::table('invoices')->where('company_id', $company->id)->delete();

    $deliveryNote = DeliveryNote::factory()->create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'delivery_date' => '2026-11-11',
        'delivery_number' => 'POC-F11A-BILLED-'.time(),
        'status' => DeliveryNote::STATUS_DELIVERED,
        'sub_total' => 100,
        'tax' => 5,
        'total' => 105,
        'exchange_rate' => 1,
        'unique_hash' => 'poc-f11a-billed-'.time(),
    ]);

    $linkedInvoice = \Crater\Models\Invoice::create([
        'creator_id' => $user->id,
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'delivery_note_id' => $deliveryNote->id,
        'invoice_date' => now()->format('Y-m-d'),
        'invoice_number' => 'POC-F11A-INV-'.time(),
        'sequence_number' => 1,
        'customer_sequence_number' => 1,
        'reference_number' => 'POC-F11A-INV',
        'template_name' => 'invoice1',
        'status' => \Crater\Models\Invoice::STATUS_DRAFT,
        'paid_status' => \Crater\Models\Invoice::STATUS_UNPAID,
        'sub_total' => 100,
        'total' => 105,
        'tax' => 5,
        'discount' => 0,
        'discount_val' => 0,
        'discount_type' => 'percentage',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'due_amount' => 105,
        'exchange_rate' => 1,
        'sales_tax_type' => 'none',
        'sales_tax_address_type' => 'none',
        'unique_hash' => 'poc-f11a-linked-inv-'.time(),
    ]);

    $deliveryNote->refresh();
    $payload = (new DeliveryNoteResource($deliveryNote))->toArray(request());

    expect($payload['already_billed'])->toBeTrue();

    // Idempotencia del test: cleanup del invoice_id no es necesario.
    $linkedInvoice->delete();
});

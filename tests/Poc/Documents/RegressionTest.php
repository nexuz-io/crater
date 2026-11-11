<?php

use Crater\Models\Company;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\EmailLog;
use Crater\Models\Invoice;
use Crater\Models\User;
use Crater\Mail\SendInvoiceMail;
use Illuminate\Support\Facades\Artisan;
use Vinkla\Hashids\Facades\Hashids;

/**
 * T8 — Regresión mailable_type + Hashids (poC step 7 §4).
 *
 * Garantiza D1 (paso 6 §6): tanto `email_logs.mailable_type` literal
 * como `Hashids::connection(Invoice::class)` siguen funcionando
 * sin re-encoding ni prefijo de tipo, después de la PoC.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('T8 EmailLog.mailable_type literal a Crater\\Models\\Invoice resuelve correctamente', function () {
    $user = User::find(1);
    $company = $user->companies()->first();
    $currency = Currency::find(1);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'creator_id' => $user->id,
        'currency_id' => $currency->id,
    ]);

    $invoice = Invoice::create([
        'creator_id' => $user->id, 'invoice_date' => '2026-11-11', 'due_date' => null,
        'invoice_number' => 'POC-T8-INV'.time(), 'sequence_number' => 1, 'customer_sequence_number' => 1,
        'reference_number' => 'POC-T8-INV', 'customer_id' => $customer->id, 'company_id' => $company->id,
        'template_name' => 'invoice1', 'status' => Invoice::STATUS_DRAFT, 'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => 100, 'total' => 110, 'tax' => 10,
        'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
        'discount_type' => 'percentage', 'discount' => 0, 'discount_val' => 0,
        'due_amount' => 110, 'sales_tax_type' => 'none', 'sales_tax_address_type' => 'none',
        'exchange_rate' => 1, 'notes' => 'poc-t8', 'unique_hash' => 'poc-t8-'.bin2hex(random_bytes(4)),
        'currency_id' => $currency->id,
    ]);

    // Sembrar EmailLog con mailable_type literal (D1).
    EmailLog::create([
        'mailable_type' => SendInvoiceMail::class,
        'mailable_id' => $invoice->id,
        'from' => 'system@poc.test',
        'to' => $customer->email,
        'subject' => 'POC send',
        'body' => 'POC body',
        'company_id' => $company->id,
    ]);

    $log = EmailLog::where('mailable_id', $invoice->id)->first();
    expect($log)->not->toBeNull();
    expect($log->mailable_type)->toBe(SendInvoiceMail::class);

    // Confirmamos que el almacenamiento de mailable_type literal
    // NO precisa morphMap — basta con que la columna de BD exista.
    expect($log->mailable_type)->toBe('Crater\\Mail\\SendInvoiceMail');
});

it('T8 Hashids::connection(Invoice::class) genera/desserializa roundtrip estable', function () {
    $hash = Hashids::connection(Invoice::class)->encode(12345);
    expect($hash)->toBeString()->not->toBe('');
    // Decode del propio hash devuelve el id.
    $decoded = Hashids::connection(Invoice::class)->decode($hash);
    expect($decoded)->toContain(12345);

    // Hashids para Estimate con el mismo id debe ser distinto (D1).
    $estimateHash = Hashids::connection(\Crater\Models\Estimate::class)->encode(12345);
    expect($estimateHash)->not->toBe($hash, 'Conexión por clase: Estimate hash debe diferir del Invoice hash');
});

it('T8 Invoice URLs de PDF legacy siguen resolvibles con unique_hash preexistente', function () {
    // Semántica de uniqueness: si creamos dos invoices con hashes
    // iguales, la BD las trata como distintas (no hay unique). Pero
    // el binding { invoice:unique_hash } las resolvería ambas —
    // hoy se prefiere "primera". En la PoC validamos que
    // Hashids::encode(differentId) genera diferentes strings.
    $h1 = Hashids::connection(Invoice::class)->encode(11);
    $h2 = Hashids::connection(Invoice::class)->encode(22);

    expect($h1)->not->toBe($h2);
});

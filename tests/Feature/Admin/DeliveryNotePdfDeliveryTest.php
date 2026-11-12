<?php

/**
 * Tests del pipeline "PDF DeliveryNote" introducido por plan 05
 * + plan 09 §F11.
 *
 * Cubre:
 *   - DeliveryNote::generatePDF/getPDFData están disponibles y
 *     invocables desde el modelo (pipeline compartido con
 *     Invoice/Estimate vía GeneratesPdfTrait).
 *   - Las rutas web /delivery-notes/pdf/{hash} resuelven a la
 *     acción correcta.
 *   - El seeder CraterVersion610 es idempotente.
 *   - El job GenerateDeliveryNotePdfJob despacha sin parámetros
 *     dinámicos inválidos.
 */

use Crater\Http\Controllers\V1\PDF\DeliveryNotePdfController as AppDeliveryNotePdfController;
use Crater\Http\Controllers\V1\Customer\DeliveryNotePdfController as CustomerDeliveryNotePdfController;
use Crater\Jobs\GenerateDeliveryNotePdfJob;
use Crater\Mail\DeliveryNoteViewedMail;
use Crater\Mail\SendDeliveryNoteMail;
use Crater\Models\DeliveryNote;
use Database\Seeders\CraterVersion610;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('plan 05: DeliveryNote model expone los metodos PDF del contrato', function () {
    $dn = new DeliveryNote();

    expect(method_exists($dn, 'generatePDF'))->toBeTrue();
    expect(method_exists($dn, 'getPDFData'))->toBeTrue();
    expect(method_exists($dn, 'getGeneratedPDF'))->toBeTrue();
    expect(method_exists($dn, 'getGeneratedPDFOrStream'))->toBeTrue();
    expect(method_exists($dn, 'pdfView'))->toBeTrue();
    expect(method_exists($dn, 'getCompanyAddress'))->toBeTrue();
    expect(method_exists($dn, 'getShippingAddress'))->toBeTrue();
    expect(method_exists($dn, 'getBillingAddress'))->toBeTrue();
});

test('plan 05: pdfView apunta a la plantilla blade correcta', function () {
    $dn = new DeliveryNote();

    expect($dn->pdfView())->toBe('app.pdf.delivery-note.delivery-note-1');
});

test('plan 05: GenerateDeliveryNotePdfJob se construye con delivery_number válido', function () {
    $dn = DeliveryNote::factory()->create();
    $job = new GenerateDeliveryNotePdfJob($dn);

    expect($job->deliveryNote)->toBeInstanceOf(DeliveryNote::class);
    expect($job->deliveryNote->delivery_number)->not->toBeEmpty();
    expect($job->deleteExistingFile)->toBeFalse();
});

test('plan 05: GenerateDeliveryNotePdfJob acepta deleteExistingFile=true', function () {
    $dn = DeliveryNote::factory()->create();
    $job = new GenerateDeliveryNotePdfJob($dn, true);

    expect($job->deleteExistingFile)->toBeTrue();
});

test('plan 05: rutas web del PDF publico resuelven al controller esperado', function () {
    $routes = collect(Illuminate\Support\Facades\Route::getRoutes());

    $publicPdfRoute = $routes->first(function ($r) {
        return $r->uri === 'delivery-notes/pdf/{delivery_note}'
            && in_array('GET', $r->methods, true);
    });

    expect($publicPdfRoute)->not->toBeNull();
    // The web route uses `unique_hash` for route-model binding; the
    // raw URI template matches both `{delivery_note}` and
    // `{delivery_note:unique_hash}`. Either is acceptable.
    $publicPdfUri = $publicPdfRoute->uri;
    expect($publicPdfUri)
        ->toMatch('/^delivery-notes\/pdf\/\{delivery_note(?::unique_hash)?\}$/');
    expect($publicPdfRoute->getAction()['controller'])
        ->toBe(AppDeliveryNotePdfController::class);

    $previewRoute = $routes->first(function ($r) {
        return $r->uri === 'delivery-notes/{email_log}'
            && in_array('GET', $r->methods, true);
    });

    expect($previewRoute)->not->toBeNull();
    expect($previewRoute->getAction()['controller'])
        ->toBe(CustomerDeliveryNotePdfController::class.'@getDeliveryNote');

    $tokenRoute = $routes->first(function ($r) {
        return str_starts_with($r->uri, 'delivery-notes/view/{email_log}')
            && in_array('GET', $r->methods, true);
    });

    expect($tokenRoute)->not->toBeNull();
    expect($tokenRoute->getName())->toBe('delivery-note');
    expect($tokenRoute->getAction()['controller'])
        ->toBe(CustomerDeliveryNotePdfController::class.'@getPdf');
});

test('plan 05: SendDeliveryNoteMail y DeliveryNoteViewedMail existen como clases mailable', function () {
    expect(class_exists(SendDeliveryNoteMail::class))->toBeTrue();
    expect(class_exists(DeliveryNoteViewedMail::class))->toBeTrue();
});

test('plan 05: SendDeliveryNoteMail puede construirse para un albarán real', function () {
    Mail::fake();

    $dn = DeliveryNote::factory()->create();

    $mail = new SendDeliveryNoteMail([
        'from' => 'alice@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Test Albarán',
        'body' => '<p>Estimado cliente…</p>',
        'delivery_note' => [
            'id' => $dn->id,
            'company_id' => $dn->company_id,
        ],
        'attach' => ['data' => null],
    ]);

    expect($mail)->toBeInstanceOf(SendDeliveryNoteMail::class);
    expect($mail->data['delivery_note']['id'])->toBe($dn->id);
});

test('plan 05: CraterVersion610 seeder es idempotente (puede correr dos veces)', function () {
    Artisan::call('db:seed', ['--class' => 'CraterVersion610', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'CraterVersion610', '--force' => true]);

    expect(class_exists(CraterVersion610::class))->toBeTrue();

    expect(CraterVersion610::ABILITIES)->toContain('view-delivery-note');
    expect(CraterVersion610::ABILITIES)->toContain('convert-delivery-note-invoice');
    expect(CraterVersion610::TARGET_ROLES)->toContain('super admin');
});

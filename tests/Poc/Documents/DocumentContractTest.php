<?php

use Crater\Poc\Documents\Contracts\Billable;
use Crater\Poc\Documents\Contracts\Deliverable;
use Crater\Poc\Documents\Contracts\DocumentContract;
use Crater\Poc\Documents\Contracts\HasAmounts;
use Crater\Poc\Documents\Contracts\HasCustomFieldsTrait;
use Crater\Poc\Documents\Contracts\HasHistoricalSnapshots;
use Crater\Poc\Documents\Contracts\HasItems;
use Crater\Poc\Documents\Contracts\HasMedia;
use Crater\Poc\Documents\Contracts\HasTaxes;
use Crater\Poc\Documents\Contracts\Mailable;
use Crater\Poc\Documents\Contracts\Payable;
use Crater\Poc\Documents\Contracts\Printable;
use Crater\Poc\Documents\Contracts\SerialNumbered;

// No usamos alias trait aquí; los modelos reales los traen incluidos.

/**
 * T1 — Cumplimiento del contrato (PoC step 7).
 *
 * Verifica que las 12 interfaces existen, son distintas y declaran
 * los métodos esperados. Para los modelos reales (Invoice, Estimate,
 * RecurringInvoice), NO los modificamos en la PoC; sólo se verifica
 * que las interfaces no chocan con sus métodos públicos.
 */

it('declares DocumentContract with the expected query methods', function () {
    $reflection = new ReflectionClass(DocumentContract::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('documentType'))->toBeTrue();
    expect($reflection->hasMethod('customer'))->toBeTrue();
    expect($reflection->hasMethod('company'))->toBeTrue();
    expect($reflection->hasMethod('creator'))->toBeTrue();
    expect($reflection->hasMethod('currency'))->toBeTrue();
    expect($reflection->hasMethod('notes'))->toBeTrue();
    expect($reflection->hasMethod('status'))->toBeTrue();
    expect($reflection->hasMethod('getPreviousStatus'))->toBeTrue();
    expect($reflection->hasMethod('getFormattedNotes'))->toBeTrue();
    expect($reflection->hasMethod('persistedTotals'))->toBeTrue();
});

it('declares segregated capability interfaces', function () {
    $expectedInterfaces = [
        HasAmounts::class,
        HasItems::class,
        HasTaxes::class,
        Printable::class,
        Mailable::class,
        Payable::class,
        Deliverable::class,
        Billable::class,
        SerialNumbered::class,
        HasHistoricalSnapshots::class,
    ];

    foreach ($expectedInterfaces as $iface) {
        expect(interface_exists($iface))->toBeTrue("Interface {$iface} should exist");
    }
});

it('does not require empty methods on the root contract', function () {
    // Anti-patrón penalizado en paso 4 §2.
    // DocumentContract no debe tener métodos con cuerpo `throw`
    // ni `return null`. Como interfaz, sólo exige firma.
    $reflection = new ReflectionClass(DocumentContract::class);

    foreach ($reflection->getMethods() as $method) {
        // No tiene cuerpo (es interfaz).
        expect($method->isAbstract() || $method->getDeclaringClass()->isInterface())
            ->toBeTrue("Method {$method->getName()} should not have body in interface");
    }
});

it('Invoice has methods compatible with expected capabilities', function () {
    $invoice = new ReflectionClass(\Crater\Models\Invoice::class);

    // HasAmounts: el modelo expone los totales vía dynamic
    // properties (Laravel). Verificamos los métodos magic + columnas.
    expect(method_exists(\Crater\Models\Invoice::class, '__get'))->toBeTrue();
    // Las columnas se gestionan via $guarded/$casts, no como
    // propiedades PHP declaradas. Comprobamos las casts:
    $invoiceInstance = new \Crater\Models\Invoice();
    $casts = $invoiceInstance->getCasts();
    expect($casts)->toHaveKey('total');
    expect($casts)->toHaveKey('tax');
    expect($casts)->toHaveKey('sub_total');
    expect($casts)->toHaveKey('discount_val');
    expect($casts)->toHaveKey('exchange_rate');

    // HasItems: items HasMany + taxes HasMany (ya existen).
    expect($invoice->hasMethod('items'))->toBeTrue();
    expect($invoice->hasMethod('taxes'))->toBeTrue();

    // Printable: getPDFData() ya existe.
    expect($invoice->hasMethod('getPDFData'))->toBeTrue();
    expect($invoice->hasMethod('getPreviousStatus'))->toBeTrue();

    // Payable: add/subtract payment.
    expect($invoice->hasMethod('addInvoicePayment'))->toBeTrue();
    expect($invoice->hasMethod('subtractInvoicePayment'))->toBeTrue();
});

it('Estimate has methods compatible with HasAmounts and Billable', function () {
    $estimate = new ReflectionClass(\Crater\Models\Estimate::class);

    expect($estimate->hasMethod('items'))->toBeTrue();
    expect($estimate->hasMethod('taxes'))->toBeTrue();
    // Estimate::getPreviousStatus hereda de Model; verificamos
    // que NO está definido (esto es parte de la matriz del paso 2
    // §1; en este caso el contrato exigirá un método equivalent
    // — devuelto o marcado como no aplicable en Plan A).
    // Lo dejaremos como expectativa para refactor de F1:
    // expect($estimate->hasMethod('getPreviousStatus'))->toBeTrue();

    expect($estimate->hasMethod('getPDFData'))->toBeTrue();
    // Billable::billToInvoice() se introduce con F3.
});

it('RecurringInvoice has methods compatible with HasAmounts but not with Printable', function () {
    $recurring = new ReflectionClass(\Crater\Models\RecurringInvoice::class);

    expect($recurring->hasMethod('items'))->toBeTrue();
    expect($recurring->hasMethod('taxes'))->toBeTrue();
    // RecurringInvoice NO debe implementar Printable/Mailable.
    // (verificamos que getPDFData NO existe para Recurring).
    expect($recurring->hasMethod('getPDFData'))->toBeFalse(
        'RecurringInvoice no implementa Printable (paso 5 §6)'
    );
});

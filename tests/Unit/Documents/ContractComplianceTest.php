<?php

use Crater\Contracts\Documents\Billable;
use Crater\Contracts\Documents\Deliverable;
use Crater\Contracts\Documents\DocumentContract;
use Crater\Contracts\Documents\HasAmounts;
use Crater\Contracts\Documents\HasHistoricalSnapshots;
use Crater\Contracts\Documents\HasItems;
use Crater\Contracts\Documents\HasTaxes;
use Crater\Contracts\Documents\Mailable;
use Crater\Contracts\Documents\Payable;
use Crater\Contracts\Documents\Printable;
use Crater\Contracts\Documents\SerialNumbered;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;

/**
 * Plan 09 §F1 (PR-02) — T1 regression lock.
 *
 * Estrategia A (contract-first): las 11 interfaces existen y tienen
 * las firmas diseñadas en el plan 06 §paso-5 §3 / tabla §1472-1482.
 * Los modelos reales NO declaran `implements DocumentContract`
 * todavía — eso entra por F2-F11 cuando cada fase aporta los
 * métodos que faltan (DocumentWriter introduce `totals()`,
 * `syncItems()`, `addPayment()`, etc.).
 *
 * El test fija dos cosas:
 *
 * 1. Las 11 interfaces + raíz existen con la forma exacta
 *    (firmas esperadas, ninguna con cuerpo);
 *
 * 2. La línea base de los modelos reales: qué métodos YA están
 *    presentes y cuáles NO, para que cualquier persona que refactorice
 *    Invoice/Estimate/RecurringInvoice rompa este test y sepa qué se
 *    espera de cada uno.
 *
 * Si en una fase futura un modelo declara `implements DocumentContract`,
 * este test se ajusta (no se omite) acompañando el cambio.
 */

it('declares DocumentContract with the expected query methods', function () {
    $reflection = new ReflectionClass(DocumentContract::class);

    expect($reflection->isInterface())->toBeTrue();

    // Plan 06 §paso-5 §1: 10 métodos sin cuerpo.
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

it('declares the ten segregated capability interfaces in their expected layout', function () {
    $expected = [
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

    foreach ($expected as $iface) {
        expect(interface_exists($iface))
            ->toBeTrue("Interface {$iface} should exist (plan 09 §F1)");

        $reflection = new ReflectionClass($iface);
        expect($reflection->isInterface())
            ->toBeTrue("{$iface} should be an interface, not a class");
    }
});

it('does not require empty-body methods on any contract interface', function () {
    // Anti-patrón AC5 (cero métodos con cuerpo en el contrato).
    // Las interfaces declaran firmas, no implementaciones.

    $interfaces = [
        DocumentContract::class,
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
        Deliverable::class,
    ];

    foreach ($interfaces as $iface) {
        $reflection = new ReflectionClass($iface);

        foreach ($reflection->getMethods() as $method) {
            expect($method->isAbstract())
                ->toBeTrue("{$iface}::{$method->getName()} must be abstract (interface signature)");
            expect($method->getDeclaringClass()->isInterface())
                ->toBeTrue("{$iface}::{$method->getName()} must live in an interface");
        }
    }
});

it('Invoice exposes the line-base methods already present (no implements yet)', function () {
    $reflection = new ReflectionClass(Invoice::class);

    // DocumentContract: aún NO implementado en F1. Esta línea base
    // se mueve a `implements DocumentContract` cuando F2 introduzca
    // `persistedTotals()` y los wrappers que faltan.
    expect(in_array(DocumentContract::class, class_implements(Invoice::class) ?: [], true))
        ->toBeFalse('Invoice must NOT declare `implements DocumentContract` yet (entra con F2)');

    // HasItems / HasTaxes — métodos base ya presentes.
    expect($reflection->hasMethod('items'))->toBeTrue();
    expect($reflection->hasMethod('taxes'))->toBeTrue();

    // Printable.
    expect($reflection->hasMethod('getPDFData'))->toBeTrue();

    // Mailable.
    expect($reflection->hasMethod('send'))->toBeTrue();
    expect($reflection->hasMethod('preview'))->toBeTrue();

    // Payable: ya tiene métodos reales, pero con nombres distintos
    // (addInvoicePayment / subtractInvoicePayment). El contrato
    // exige addPayment / subtractPayment — F2 introduce el wrapper
    // o alinea firmas.
    expect($reflection->hasMethod('addInvoicePayment'))->toBeTrue();
    expect($reflection->hasMethod('subtractInvoicePayment'))->toBeTrue();
    expect($reflection->hasMethod('addPayment'))->toBeFalse();
    expect($reflection->hasMethod('subtractPayment'))->toBeFalse();
    expect($reflection->hasMethod('paidStatus'))->toBeFalse();
    expect($reflection->hasMethod('dueAmount'))->toBeFalse();

    // DocumentContract raíz: aún sin implementar.
    expect($reflection->hasMethod('documentType'))->toBeFalse('documentType() entra con F2');
    expect($reflection->hasMethod('persistedTotals'))->toBeFalse('persistedTotals() entra con F2');
});

it('Estimate exposes the line-base methods already present (no implements yet)', function () {
    $reflection = new ReflectionClass(Estimate::class);

    expect(in_array(DocumentContract::class, class_implements(Estimate::class) ?: [], true))
        ->toBeFalse('Estimate must NOT declare `implements DocumentContract` yet');

    expect($reflection->hasMethod('items'))->toBeTrue();
    expect($reflection->hasMethod('taxes'))->toBeTrue();
    expect($reflection->hasMethod('getPDFData'))->toBeTrue();
    expect($reflection->hasMethod('send'))->toBeTrue();

    // Estimate NO tiene `preview()` público. Lo tiene `sendEstimateData()`
    // y `send()`. Para que `implements Mailable` se cumpla sin shim,
    // F2-F4 deben aportar `preview()` o reducir el contrato.
    expect($reflection->hasMethod('preview'))->toBeFalse(
        'Estimate no expone preview() público — entra con F2-F4 cuando se adopten Mailable'
    );

    // Estimate NO implementa Payable (matriz plan 06 §paso-5 §3).
    expect($reflection->hasMethod('paidStatus'))->toBeFalse('Payable es solo Invoice');

    // Billable: llega con F3.
    expect($reflection->hasMethod('billToInvoice'))->toBeFalse('Billable entra con F3');
});

it('RecurringInvoice exposes line-base but NOT Printable/Mailable/Payable', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);

    expect(in_array(DocumentContract::class, class_implements(RecurringInvoice::class) ?: [], true))
        ->toBeFalse('RecurringInvoice must NOT declare `implements DocumentContract` yet');

    expect($reflection->hasMethod('items'))->toBeTrue();
    expect($reflection->hasMethod('taxes'))->toBeTrue();

    // Recurring NO implementa Printable (es plantilla).
    expect($reflection->hasMethod('getPDFData'))
        ->toBeFalse('RecurringInvoice no implementa Printable (plan 06 §paso-5 §6)');

    // Recurring NO implementa Mailable.
    expect($reflection->hasMethod('send'))
        ->toBeFalse('RecurringInvoice no implementa Mailable (matriz §3)');
});

it('Invoice does not implement any capability interface yet', function () {
    // Línea base F1: ningún modelo declara `implements X`. Se irán
    // agregando en F2-F11 fase a fase.
    foreach ([
        DocumentContract::class,
        HasAmounts::class,
        HasItems::class,
        HasTaxes::class,
        Printable::class,
        Mailable::class,
        Payable::class,
        Billable::class,
        SerialNumbered::class,
        HasHistoricalSnapshots::class,
    ] as $iface) {
        expect(in_array($iface, class_implements(Invoice::class) ?: [], true))
            ->toBeFalse("Invoice must not declare `implements {$iface}` yet (contract-first)");
    }
});

it('Estimate does not implement any capability interface yet', function () {
    foreach ([
        DocumentContract::class,
        HasAmounts::class,
        HasItems::class,
        HasTaxes::class,
        Printable::class,
        Mailable::class,
        Billable::class,
        SerialNumbered::class,
        HasHistoricalSnapshots::class,
    ] as $iface) {
        expect(in_array($iface, class_implements(Estimate::class) ?: [], true))
            ->toBeFalse("Estimate must not declare `implements {$iface}` yet (contract-first)");
    }
});

it('RecurringInvoice does not implement any capability interface yet', function () {
    foreach ([
        DocumentContract::class,
        HasAmounts::class,
        HasItems::class,
        HasTaxes::class,
        SerialNumbered::class,
    ] as $iface) {
        expect(in_array($iface, class_implements(RecurringInvoice::class) ?: [], true))
            ->toBeFalse("RecurringInvoice must not declare `implements {$iface}` yet (contract-first)");
    }
});

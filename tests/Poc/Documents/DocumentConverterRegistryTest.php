<?php

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;
use Crater\Poc\Documents\Services\DocumentConverterInterface;
use Crater\Poc\Documents\Services\DocumentConverterRegistry;
use Crater\Poc\Documents\Services\Converters\CloneDocumentConverter;
use Crater\Poc\Documents\Services\Converters\EstimateToInvoiceConverter;
use Crater\Poc\Documents\Services\Converters\RecurringToInvoiceConverter;

/**
 * T6 — DocumentConverterRegistry + DespachoIndex dispatch (PoC step 7 §4).
 *
 * 1. El registry indexa converters por (sourceType, targetType).
 * 2. convertersFor(sourceType) devuelve los converters que arrancan
 *    del tipo dado.
 * 3. No se usa match/switch (plan 06 §8).
 */

it('registry indexes converters by sourceType + targetType key', function () {
    $registry = new DocumentConverterRegistry();
    $registry->register(new EstimateToInvoiceConverter());
    $registry->register(new RecurringToInvoiceConverter());

    $e = $registry->get('estimate', 'invoice');
    expect($e)->toBeInstanceOf(EstimateToInvoiceConverter::class);
    expect($e->sourceType())->toBe('estimate');
    expect($e->targetType())->toBe('invoice');

    $r = $registry->get('recurring-invoice', 'invoice');
    expect($r)->toBeInstanceOf(RecurringToInvoiceConverter::class);
});

it('convertersFor(sourceType) devuelve converters de ese origen', function () {
    $registry = new DocumentConverterRegistry();
    $registry->register(new EstimateToInvoiceConverter());
    $registry->register(new RecurringToInvoiceConverter());
    $registry->register(new CloneDocumentConverter('estimate', 'estimate'));

    $found = [];
    foreach ($registry->convertersFor('estimate') as $c) {
        $found[] = $c->sourceType().'->'.$c->targetType();
    }

    expect($found)->toContain('estimate->invoice');
    expect($found)->toContain('estimate->estimate');

    $recFound = [];
    foreach ($registry->convertersFor('recurring-invoice') as $c) {
        $recFound[] = $c->sourceType().'->'.$c->targetType();
    }
    expect($recFound)->toContain('recurring-invoice->invoice');
});

it('CloneDocumentConverter para estimate->estimate (no requiere switch)', function () {
    $c = new CloneDocumentConverter('estimate', 'estimate');

    expect($c->sourceType())->toBe('estimate');
    expect($c->targetType())->toBe('estimate');
});

it('CloneDocumentConverter rechaza sourceType !== targetType', function () {
    $c = new CloneDocumentConverter('estimate', 'recurring-invoice');

    // Cualquier DocumentContract (incluso uno anónimo) basta.
    $doc = new class implements \Crater\Poc\Documents\Contracts\DocumentContract {
        public function documentType(): string { return 'estimate'; }
        public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { throw new \LogicException('n/a'); }
        public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo { throw new \LogicException('n/a'); }
        public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo { throw new \LogicException('n/a'); }
        public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo { throw new \LogicException('n/a'); }
        public function notes(): ?string { return null; }
        public function status(): string { return 'DRAFT'; }
        public function getPreviousStatus(): string { return 'DRAFT'; }
        public function getFormattedNotes(): ?string { return null; }
        public function persistedTotals(): \Crater\Poc\Documents\Dto\DocumentTotals { return new \Crater\Poc\Documents\Dto\DocumentTotals(); }
    };

    expect(fn () => $c->convert($doc))->toThrow(\InvalidArgumentException::class);
});

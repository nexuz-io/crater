<?php

namespace Crater\Contracts\Documents;

use Crater\DataObjects\Documents\DocumentTotals;

/**
 * Plan 09 §F1 (matriz §3 del plan 06): todos los tipos emitidos
 * — Invoice, Estimate, RecurringInvoice, DeliveryNote — la
 * implementan. La lógica de cálculo entra en F2
 * (DocumentTotalsCalculator).
 */
interface HasAmounts
{
    public function totals(): DocumentTotals;

    public function recompute(): void;
}

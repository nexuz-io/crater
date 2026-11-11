<?php

namespace Crater\DataObjects\Documents;

/**
 * Snapshot inmutable de los totales persistidos.
 *
 * Plan 09 §F1: type-hint obligatorio de DocumentContract::persistedTotals()
 * y HasAmounts::totals(). La semántica completa, factorías de cálculo y
 * conversión desde Model llegan en F2 (DocumentTotalsCalculator) y F7
 * (persistencia efectiva). Esta primera versión declara el shape para
 * que las interfaces del contrato puedan compilar.
 */
final class DocumentTotals
{
    public function __construct(
        public readonly ?int $subTotal = null,
        public readonly ?int $tax = null,
        public readonly ?int $total = null,
        public readonly ?int $discountVal = null,
        public readonly ?string $discountType = null,
        public readonly ?float $discount = null,
        public readonly ?int $baseTotal = null,
        public readonly ?int $baseSubTotal = null,
        public readonly ?int $baseTax = null,
        public readonly ?int $baseDiscountVal = null,
        public readonly ?int $dueAmount = null,
        public readonly ?int $baseDueAmount = null,
    ) {
    }
}

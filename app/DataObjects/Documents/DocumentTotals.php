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

    /**
     * Construye un DocumentTotals a partir de los atributos del
     * modelo. Acepta modelos con cualquier subset de las columnas
     * persistidas (Invoice, Estimate, RecurringInvoice toleran
     * campos NULL).
     */
    public static function fromModel(object $model): self
    {
        return new self(
            subTotal: isset($model->sub_total) ? (int) $model->sub_total : null,
            tax: isset($model->tax) ? (int) $model->tax : null,
            total: isset($model->total) ? (int) $model->total : null,
            discountVal: isset($model->discount_val) ? (int) $model->discount_val : null,
            discountType: $model->discount_type ?? null,
            discount: isset($model->discount) ? (float) $model->discount : null,
            baseTotal: isset($model->base_total) ? (int) $model->base_total : null,
            baseSubTotal: isset($model->base_sub_total) ? (int) $model->base_sub_total : null,
            baseTax: isset($model->base_tax) ? (int) $model->base_tax : null,
            baseDiscountVal: isset($model->base_discount_val) ? (int) $model->base_discount_val : null,
            dueAmount: isset($model->due_amount) ? (int) $model->due_amount : null,
            baseDueAmount: isset($model->base_due_amount) ? (int) $model->base_due_amount : null,
        );
    }
}

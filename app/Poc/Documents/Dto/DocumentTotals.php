<?php

namespace Crater\Poc\Documents\Dto;

/**
 * Snapshot inmutable de los totales persistidos.
 *
 * PoC step 5 §5. Una sola fuente para consumidores; evita pasar
 * arrays magos. Ningún campo cambia de significado entre tipos.
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
    ) {}

    /**
     * Calcula DocumentTotals a partir de un modelo con los campos
     * persistidos. Acepta tanto Invoice como Estimate, ambos usan
     * los mismos nombres de columna (paso 2 §1 matriz).
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

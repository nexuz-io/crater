<?php

namespace Crater\Poc\Documents\Dto;

/**
 * Snapshot inmutable de una línea persistida.
 *
 * PoC step 5 §5. Habilita convertir el array de request a fila sin
 * side-effects.
 */
final class LineSnapshot
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly float $quantity = 1.0,
        public readonly int $price = 0,
        public readonly int $tax = 0,
        public readonly int $total = 0,
        public readonly ?int $discountVal = null,
        public readonly ?string $discountType = null,
        public readonly ?float $discount = null,
        public readonly ?float $exchangeRate = null,
        public readonly ?int $itemId = null,
        public readonly int $companyId = 0,
        public readonly ?string $deliverables = null,
        public readonly ?string $deliveryDelay = null,
        public readonly ?string $terms = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            description: $data['description'] ?? null,
            quantity: (float) ($data['quantity'] ?? 1),
            price: (int) ($data['price'] ?? 0),
            tax: (int) ($data['tax'] ?? 0),
            total: (int) ($data['total'] ?? 0),
            discountVal: isset($data['discount_val']) ? (int) $data['discount_val'] : null,
            discountType: $data['discount_type'] ?? null,
            discount: isset($data['discount']) ? (float) $data['discount'] : null,
            exchangeRate: isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : null,
            itemId: isset($data['item_id']) ? (int) $data['item_id'] : null,
            companyId: (int) ($data['company_id'] ?? 0),
            deliverables: $data['deliverables'] ?? null,
            deliveryDelay: $data['delivery_delay'] ?? null,
            terms: $data['terms'] ?? null,
        );
    }
}

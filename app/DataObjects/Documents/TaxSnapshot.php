<?php

namespace Crater\DataObjects\Documents;

/**
 * Snapshot inmutable de un tax persistido (plan 09 §F7).
 *
 * Acepta tanto el payload de la tabla polimórfica `taxes` (que
 * almacena Invoice, Estimate, RecurringInvoice y DeliveryNote tax)
 * como los arrays del request.
 */
final class TaxSnapshot
{
    public function __construct(
        public readonly int $taxTypeId,
        public readonly int $amount,
        public readonly float $percent,
        public readonly bool $compoundTax,
        public readonly string $name,
        public readonly ?float $exchangeRate = null,
        public readonly ?int $currencyId = null,
        public readonly int $companyId = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            taxTypeId: (int) ($data['tax_type_id'] ?? 0),
            amount: (int) ($data['amount'] ?? 0),
            percent: (float) ($data['percent'] ?? 0),
            compoundTax: (bool) ($data['compound_tax'] ?? false),
            name: (string) ($data['name'] ?? ''),
            exchangeRate: isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : null,
            currencyId: isset($data['currency_id']) ? (int) $data['currency_id'] : null,
            companyId: (int) ($data['company_id'] ?? 0),
        );
    }
}

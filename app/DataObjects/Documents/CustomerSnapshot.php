<?php

namespace Crater\DataObjects\Documents;

use Carbon\Carbon;
use Crater\Models\Customer;

/**
 * Snapshot inmutable del cliente (plan 09 §F7).
 *
 * Implementado como DTO vacío en F1 para que
 * HasHistoricalSnapshots::customerSnapshot() pudiera compilar;
 * en F7 añadimos `fromModel()` para que `DocumentSnapshotter` pueda
 * construir snapshots vivos desde el modelo Customer sin tocar
 * persistencia. La persistencia efectiva (columna o JSON en notes)
 * entra como follow-up del gate G3.
 */
final class CustomerSnapshot
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $contactName = null,
        public readonly ?string $companyName = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $website = null,
        public readonly ?int $currencyId = null,
        public readonly ?Carbon $capturedAt = null,
        public readonly string $locale = 'en',
    ) {
    }

    public static function fromModel(Customer $customer, ?int $currencyId = null): self
    {
        return new self(
            name: (string) ($customer->name ?? ''),
            contactName: $customer->contact_name ?? null,
            companyName: $customer->company_name ?? null,
            email: $customer->email ?? null,
            phone: $customer->phone ?? null,
            website: $customer->website ?? null,
            currencyId: $currencyId ?? ($customer->currency_id ?? null),
            capturedAt: Carbon::now(),
            locale: (string) ($customer->language ?? 'en'),
        );
    }
}

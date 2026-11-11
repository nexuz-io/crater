<?php

namespace Crater\Poc\Documents\Dto;

use Carbon\Carbon;

/**
 * Snapshot inmutable del cliente. Faltante hoy en Invoice/Estimate;
 * complemento del paso 7 §0 / 5 §5 para preservar el documento
 * aunque el cliente mute.
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
    ) {}

    public static function fromModel(\Crater\Models\Customer $customer, ?int $currencyId = null): self
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

<?php

namespace Crater\DataObjects\Documents;

use Carbon\Carbon;

/**
 * Snapshot inmutable del cliente (plan 09 §F7).
 *
 * Implementado como DTO vacío en F1 para que
 * HasHistoricalSnapshots::customerSnapshot() pueda compilar;
 * la factoría fromModel() llega cuando F7 introduzca
 * DocumentSnapshotter y la persistencia efectiva.
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
}

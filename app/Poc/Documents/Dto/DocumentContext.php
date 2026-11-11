<?php

namespace Crater\Poc\Documents\Dto;

/**
 * Contexto compartido por los conversores (paso 5 §5).
 */
final class DocumentContext
{
    public function __construct(
        public readonly \Crater\Poc\Documents\Contracts\DocumentContract $origin,
        public readonly string $targetType = 'invoice',
        public readonly ?int $companyId = null,
        public readonly ?int $byUserId = null,
        public readonly array $overrides = [],
        public readonly ?int $sourceRowId = null,
        public readonly string $currencyStrategy = 'inherit',
    ) {}
}

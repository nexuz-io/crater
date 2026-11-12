<?php

namespace Crater\DataObjects\Documents;

use Crater\Contracts\Documents\DocumentContract;

/**
 * Contexto compartido por conversores (plan 09 §F7, plan 06 §paso-5 §5).
 *
 * Encapsula los datos de orquestación que un converter necesita más
 * allá del modelo de origen: compañía destino, usuario que dispara la
 * conversión, overrides del request, y la estrategia de moneda. Esto
 * evita que los converters (F3, F4) tengan que recibir el `Request`
 * de Laravel — un DTO neutro es testeable sin HTTP.
 */
final class DocumentContext
{
    public function __construct(
        public readonly DocumentContract $origin,
        public readonly string $targetType = 'invoice',
        public readonly ?int $companyId = null,
        public readonly ?int $byUserId = null,
        public readonly array $overrides = [],
        public readonly ?int $sourceRowId = null,
        public readonly string $currencyStrategy = 'inherit',
    ) {
    }
}

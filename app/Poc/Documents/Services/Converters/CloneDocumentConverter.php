<?php

namespace Crater\Poc\Documents\Services\Converters;

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Poc\Documents\Services\DocumentConverterInterface;

/**
 * Convertidor Clone (PoC stub).
 */
final class CloneDocumentConverter implements DocumentConverterInterface
{
    public function __construct(
        private readonly string $sourceType,
        private readonly string $targetType,
    ) {}

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function targetType(): string
    {
        return $this->targetType;
    }

    public function convert(object $origin): object
    {
        if ($this->sourceType !== $this->targetType) {
            throw new \InvalidArgumentException('Clone requiere sourceType === targetType');
        }
        if ($origin instanceof Invoice) {
            return $origin;
        }
        if ($origin instanceof Estimate) {
            return $origin;
        }

        throw new \InvalidArgumentException('Clone sólo soporta Invoice o Estimate en la PoC');
    }
}

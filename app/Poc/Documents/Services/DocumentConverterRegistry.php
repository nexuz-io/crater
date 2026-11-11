<?php

namespace Crater\Poc\Documents\Services;

/**
 * DocumentConverterRegistry — POC step 5 §4.4.
 *
 * Acepta `object` para interoperar con los modelos reales.
 */
final class DocumentConverterRegistry
{
    /** @var array<string, DocumentConverterInterface> */
    private array $byKey = [];

    public function register(DocumentConverterInterface $converter): void
    {
        $key = $converter->sourceType().'->'.$converter->targetType();
        $this->byKey[$key] = $converter;
    }

    /** @return iterable<DocumentConverterInterface> */
    public function convertersFor(string $sourceType): iterable
    {
        foreach ($this->byKey as $key => $converter) {
            if (str_starts_with($key, $sourceType.'->')) {
                yield $converter;
            }
        }
    }

    public function get(string $sourceType, string $targetType): ?DocumentConverterInterface
    {
        return $this->byKey[$sourceType.'->'.$targetType] ?? null;
    }
}

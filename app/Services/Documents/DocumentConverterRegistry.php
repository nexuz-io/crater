<?php

namespace Crater\Services\Documents;

/**
 * Plan 09 §F3 — registry de converters.
 *
 * Indexa converters por la clave `sourceType->targetType`. Los
 * consumidores (controllers, jobs, AI assistant) resuelven el
 * converter sin necesidad de `switch` / `match` por tipo.
 *
 * El registry es **inmutable desde fuera**: solo `register()` añade.
 * Las clases que necesiten lógica dinámica pueden heredar o usar
 * `convertersFor()` para iterar.
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

    public function has(string $sourceType, string $targetType): bool
    {
        return isset($this->byKey[$sourceType.'->'.$targetType]);
    }

    public function get(string $sourceType, string $targetType): ?DocumentConverterInterface
    {
        return $this->byKey[$sourceType.'->'.$targetType] ?? null;
    }

    /**
     * @return iterable<DocumentConverterInterface>
     */
    public function convertersFor(string $sourceType): iterable
    {
        foreach ($this->byKey as $key => $converter) {
            if (str_starts_with($key, $sourceType.'->')) {
                yield $converter;
            }
        }
    }

    public function all(): array
    {
        return array_values($this->byKey);
    }
}

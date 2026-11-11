<?php

namespace Crater\Poc\Documents\Services;

use Crater\Poc\Documents\Contracts\DocumentContract;
use Crater\Poc\Documents\Contracts\HasAmounts;
use Crater\Poc\Documents\Contracts\HasItems;
use Crater\Poc\Documents\Contracts\HasTaxes;
use Crater\Poc\Documents\Dto\LineSnapshot;
use Crater\Poc\Documents\Dto\TaxSnapshot;
use Illuminate\Database\ConnectionInterface;

/**
 * DocumentWriter — POC step 5 §4.1.
 *
 * Atomicidad transaccional (HR7): cabecera + items + taxes en una
 * sola `DB::transaction`. Si un paso intermedio falla, rollback
 * completo.
 *
 * Forma `PoC`: acepta `object` para interoperar con los modelos
 * reales sin que aún implementen DocumentContract. F1 del plan
 * 09 estrecha el tipo cuando los `implements` se añadan.
 */
final class DocumentWriter
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  iterable<LineSnapshot|array>  $items
     * @param  iterable<TaxSnapshot|array>   $taxes
     */
    public function sync(object $doc, iterable $items, iterable $taxes): object
    {
        return $this->db->transaction(function () use ($doc, $items, $taxes) {
            if ($doc instanceof \Illuminate\Database\Eloquent\Model && method_exists($doc, 'items')) {
                $doc->items()->delete();
            }
            if ($doc instanceof \Illuminate\Database\Eloquent\Model && method_exists($doc, 'taxes')) {
                $doc->taxes()->delete();
            }

            foreach ($items as $lineData) {
                $payload = $lineData instanceof LineSnapshot
                    ? (array) $lineData
                    : $lineData;
                $doc->items()->create($payload);
            }

            foreach ($taxes as $taxData) {
                $payload = $taxData instanceof TaxSnapshot
                    ? (array) $taxData
                    : $taxData;
                $doc->taxes()->create($payload);
            }

            if ($doc instanceof HasAmounts) {
                $doc->recompute();
            }

            return $doc;
        });
    }
}

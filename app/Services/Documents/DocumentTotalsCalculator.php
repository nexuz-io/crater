<?php

namespace Crater\Services\Documents;

use Crater\Contracts\Documents\HasAmounts;
use Crater\DataObjects\Documents\DocumentTotals;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio puro (plan 09 §F2, matriz plan 06 §paso-5 §3).
 *
 * Lee los totales persistidos de un modelo y los expone como DTO
 * `DocumentTotals`. Acepta `Model` mientras los `implements` del
 * contrato no estén adoptados (entra gradualmente con F2+).
 *
 * Sin estado: las dependencias inyectadas son opcionales; cuando
 * `DocumentTotals::fromModel()` se complete, el servicio es
 * esencialmente un entry-point type-safe.
 */
final class DocumentTotalsCalculator
{
    public function for(Model $document): DocumentTotals
    {
        return DocumentTotals::fromModel($document);
    }

    /**
     * Recomputa los totales del modelo si implementa HasAmounts.
     * Para modelos sin HasAmounts, es no-op.
     */
    public function recompute(Model $document): void
    {
        if ($document instanceof HasAmounts) {
            $document->recompute();
        }
    }
}

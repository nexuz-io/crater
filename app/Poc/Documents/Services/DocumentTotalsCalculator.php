<?php

namespace Crater\Poc\Documents\Services;

use Crater\Poc\Documents\Contracts\DocumentContract;
use Crater\Poc\Documents\Contracts\HasAmounts;
use Crater\Poc\Documents\Dto\DocumentTotals;

/**
 * Servicio puro: lee los totales persistidos del modelo y los
 * devuelve como `DocumentTotals`.
 *
 * PoC step 5 §4.2. Acepta `object` para interoperar con los modelos
 * reales (Invoice/Estimate/Recurring) sin que aún implementen los
 * interfaces — F1 del plan 09 añade los `implements`. La forma
 * definitiva validará `instanceof DocumentContract`.
 */
final class DocumentTotalsCalculator
{
    public function for(object $document): DocumentTotals
    {
        return DocumentTotals::fromModel($document);
    }

    public function recompute(object $document): void
    {
        if ($document instanceof HasAmounts) {
            $document->recompute();

            return;
        }
        // No-op para documentos sin HasAmounts.
    }
}

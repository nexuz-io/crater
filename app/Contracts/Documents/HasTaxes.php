<?php

namespace Crater\Contracts\Documents;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan 09 §F1 (matriz §3 del plan 06): la implementan Invoice,
 * Estimate, RecurringInvoice y el futuro DeliveryNote. Las tres
 * clases reales ya exponen `taxes(): HasMany`; la versión
 * atómica `syncTaxes()` entra con F2 (DocumentWriter, HR7).
 */
interface HasTaxes
{
    public function taxes(): HasMany;

    /** Sustituye taxes en una transacción (atómico). */
    public function syncTaxes(iterable $taxes): void;
}

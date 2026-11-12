<?php

namespace Crater\Contracts\Documents;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan 09 §F1 (matriz §3 del plan 06): la implementan Invoice,
 * Estimate, RecurringInvoice y el futuro DeliveryNote. Las tres
 * clases reales ya exponen `items(): HasMany`; la versión
 * atómica `syncItems()` entra con F2 (DocumentWriter, HR7).
 */
interface HasItems
{
    public function items(): HasMany;

    /** Sustituye items en una transacción (atómico). */
    public function syncItems(iterable $items): void;
}

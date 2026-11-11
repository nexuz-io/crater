<?php

namespace Crater\Poc\Documents\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DocumentContract — raíz para A (composición + contratos).
 *
 * PoC step 5 §1: sólo métodos de consulta. Cualquier capacidad adicional
 * vive en interfaces segregadas (HasAmounts, Printable, …). Los métodos
 * aquí no admiten `null`/`[]`/`throw`: las clases que no apliquen deben
 * declarar `implements` sólo las interfaces que correspondan.
 */
interface DocumentContract
{
    public function documentType(): string;

    public function customer(): BelongsTo;

    public function company(): BelongsTo;

    public function creator(): BelongsTo;

    public function currency(): BelongsTo;

    public function notes(): ?string;

    public function status(): string;

    public function getPreviousStatus(): string;

    public function getFormattedNotes(): ?string;

    /** Snapshot de los totales persistidos (no recalculados). */
    public function persistedTotals(): \Crater\Poc\Documents\Dto\DocumentTotals;
}

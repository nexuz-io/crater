<?php

namespace Crater\Contracts\Documents;

use Crater\DataObjects\Documents\DocumentTotals;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan 09 §F1 — raíz del contrato de documentos.
 *
 * Cualquier modelo que represente un documento emitido debe
 * implementar `DocumentContract` junto con un subconjunto de las
 * 10 capacidades segregadas que correspondan a su tipo
 * (Invoice, Estimate, RecurringInvoice, DeliveryNote futuro).
 *
 * Las capacidades adicionales viven en interfaces segregadas
 * (HasAmounts, HasItems, Printable, Payable, ...). Los métodos
 * aquí no admiten null/[]/throw: las clases que no apliquen
 * deben declarar `implements` solo las interfaces que correspondan.
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
    public function persistedTotals(): DocumentTotals;
}

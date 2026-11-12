<?php

namespace Crater\Contracts\Documents;

/**
 * Plan 09 §F1 (matriz §3 plan 06): la implementan Invoice,
 * Estimate y el futuro DeliveryNote. RecurringInvoice queda
 * fuera por su naturaleza de plantilla (no se imprime como
 * albarán/factura, sino que cron los emite).
 *
 * La firma exacta `getGeneratedPDF(string $collection)` se
 * alineará con la introducción de `DocumentMailer` en F10
 * (plan 09 §F9-F10).
 */
interface Printable
{
    public function pdfView(): string;

    public function getPDFData(): mixed;

    /** Localización del PDF generado (cache en medios o PDF stream). */
    public function getGeneratedPDF(string $collectionName): ?array;
}

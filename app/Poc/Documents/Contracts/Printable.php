<?php

namespace Crater\Poc\Documents\Contracts;

interface Printable
{
    public function pdfView(): string;

    public function getPDFData(): mixed;

    /** Localización del PDF generado (cache en medios o PDF stream). */
    public function getGeneratedPDF(string $collectionName): ?array;
}

<?php

namespace Crater\Services\Documents;

/**
 * Plan 09 §F3 — contrato de conversores entre tipos de documento.
 *
 * El converter recibe un modelo de origen (Estimate, RecurringInvoice,
 * …) y devuelve el modelo destino (Invoice en la mayoría de los casos).
 * Los converters se registran en `DocumentConverterRegistry` indexados
 * por la dupla (sourceType, targetType), donde cada tipo es el nombre
 * lógico del documento (`estimate`, `invoice`, `recurring-invoice`,
 * `delivery-note`, …).
 *
 * Implementaciones conocimiento de los modelos concretos; la interface
 * acepta `object` para que pueda interoperar con `Estimate`,
 * `RecurringInvoice` y `DeliveryNote` (futuro) sin que tengan que
 * adoptar `DocumentContract` antes de tiempo.
 */
interface DocumentConverterInterface
{
    public function sourceType(): string;

    public function targetType(): string;

    public function convert(object $origin): object;
}

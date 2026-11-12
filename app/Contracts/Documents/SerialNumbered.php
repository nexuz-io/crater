<?php

namespace Crater\Contracts\Documents;

/**
 * Plan 09 §F1 (matriz §3 plan 06): la implementan los cuatro
 * tipos. La firma exacta se ajusta para interoperar con el
 * `SerialNumberFormatter` ya estable (plan 09 §F1, D5/D7).
 *
 * `Invoice::setNextNumbers` y `Estimate::setNextNumbers` siguen
 * siendo el camino de producción hoy; el wrapper público de
 * `nextNumber()` entra con F3/F4.
 */
interface SerialNumbered
{
    public function nextNumber(): string;

    public function getSerialContext(): array;
}

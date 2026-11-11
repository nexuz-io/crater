<?php

namespace Crater\Poc\Documents\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface HasTaxes
{
    public function taxes(): HasMany;

    /** Sustituye taxes en una transacción (atómico). */
    public function syncTaxes(iterable $taxes): void;
}

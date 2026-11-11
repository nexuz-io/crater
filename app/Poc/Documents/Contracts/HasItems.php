<?php

namespace Crater\Poc\Documents\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface HasItems
{
    public function items(): HasMany;

    /** Sustituye items en una transacción (atómico). */
    public function syncItems(iterable $items): void;
}

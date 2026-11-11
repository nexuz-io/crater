<?php

namespace Crater\Poc\Documents\Contracts;

use Crater\Poc\Documents\Dto\DocumentTotals;

interface HasAmounts
{
    public function totals(): DocumentTotals;

    public function recompute(): void;
}

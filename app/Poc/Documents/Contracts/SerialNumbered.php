<?php

namespace Crater\Poc\Documents\Contracts;

interface SerialNumbered
{
    public function nextNumber(): string;

    public function getSerialContext(): array;
}

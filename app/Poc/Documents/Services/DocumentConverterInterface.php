<?php

namespace Crater\Poc\Documents\Services;

interface DocumentConverterInterface
{
    public function sourceType(): string;

    public function targetType(): string;

    public function convert(object $origin): object;
}

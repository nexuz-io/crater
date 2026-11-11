<?php

namespace Crater\Poc\Documents\Contracts;

interface Payable
{
    public function addPayment(int $amount): void;

    public function subtractPayment(int $amount): void;

    public function paidStatus(): string;

    public function dueAmount(): int;
}

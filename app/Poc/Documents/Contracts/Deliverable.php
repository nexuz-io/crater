<?php

namespace Crater\Poc\Documents\Contracts;

use Crater\Poc\Documents\Dto\AddressSnapshot;

interface Deliverable
{
    public function deliveryStatus(): string;

    public function markAsDelivered(): void;

    public function cancel(): void;

    public function deliveryAddress(): AddressSnapshot;
}

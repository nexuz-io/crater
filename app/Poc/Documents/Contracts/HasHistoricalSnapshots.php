<?php

namespace Crater\Poc\Documents\Contracts;

use Crater\Poc\Documents\Dto\AddressSnapshot;
use Crater\Poc\Documents\Dto\CustomerSnapshot;

interface HasHistoricalSnapshots
{
    public function customerSnapshot(): CustomerSnapshot;

    public function billingAddress(): ?AddressSnapshot;

    public function shippingAddress(): ?AddressSnapshot;
}

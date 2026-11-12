<?php

namespace Crater\Contracts\Documents;

use Crater\DataObjects\Documents\AddressSnapshot;

/**
 * Plan 09 §F1 (matriz §3 plan 06): SOLO DeliveryNote la
 * implementa (Invoice/Estimate/Recurring ✗). Entra con F11 (plan
 * 09 §F11 — `DeliveryNote` operativo, plan 05 cerrado).
 */
interface Deliverable
{
    public function deliveryStatus(): string;

    public function markAsDelivered(): void;

    public function cancel(): void;

    public function deliveryAddress(): AddressSnapshot;
}

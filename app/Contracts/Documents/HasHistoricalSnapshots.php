<?php

namespace Crater\Contracts\Documents;

use Crater\DataObjects\Documents\AddressSnapshot;
use Crater\DataObjects\Documents\CustomerSnapshot;

/**
 * Plan 09 §F1 (matriz §3 plan 06): la implementan Invoice,
 * Estimate y el futuro DeliveryNote. La persistencia efectiva
 * (snapshot del customer en el momento de emisión) entra en F7
 * con DocumentSnapshotter; las interfaces ya definen las
 * firmas para que el contrato compile sin estado.
 *
 * RecurringInvoice queda fuera por su naturaleza de plantilla.
 */
interface HasHistoricalSnapshots
{
    public function customerSnapshot(): CustomerSnapshot;

    public function billingAddress(): ?AddressSnapshot;

    public function shippingAddress(): ?AddressSnapshot;
}

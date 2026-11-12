<?php

namespace Crater\DataObjects\Documents;

/**
 * Snapshot inmutable de una dirección postal.
 *
 * Plan 09 §F1: type-hint de HasHistoricalSnapshots::billingAddress()
 * /shippingAddress() y Deliverable::deliveryAddress(). La factoría
 * fromAttributes() llega con F7 (DocumentSnapshotter).
 */
final class AddressSnapshot
{
    public const TYPE_BILLING = 'billing';

    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_DELIVERY = 'delivery';

    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $countryName = null,
        public readonly ?string $state = null,
        public readonly ?string $city = null,
        public readonly ?string $street1 = null,
        public readonly ?string $street2 = null,
        public readonly ?string $phone = null,
        public readonly ?string $zip = null,
        public readonly string $type = self::TYPE_BILLING,
    ) {
    }
}

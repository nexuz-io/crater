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

    /**
     * Construye un AddressSnapshot desde los atributos planos del
     * modelo Address (que el modelo expone via getAttributes()) o
     * desde el array del request de Customers.
     */
    public static function fromAttributes(array $attrs, string $type = self::TYPE_BILLING): self
    {
        return new self(
            name: $attrs['name'] ?? null,
            countryName: $attrs['country_name'] ?? null,
            state: $attrs['state'] ?? null,
            city: $attrs['city'] ?? null,
            street1: $attrs['address_street_1'] ?? null,
            street2: $attrs['address_street_2'] ?? null,
            phone: $attrs['phone'] ?? null,
            zip: $attrs['zip'] ?? null,
            type: $type,
        );
    }
}

<?php

namespace Crater\Poc\Documents\Services;

use Crater\Poc\Documents\Dto\AddressSnapshot;
use Crater\Poc\Documents\Dto\CustomerSnapshot;
use Crater\Models\Address;
use Crater\Models\Customer;

/**
 * DocumentSnapshotter — POC step 5 §4.3.
 *
 * Crea snapshots inmutables del cliente y sus direcciones. La
 * persistencia física queda fuera de esta PoC (HR1 se cumple
 * cuando un modelo declare `HasHistoricalSnapshots`).
 */
final class DocumentSnapshotter
{
    public function snapshotCustomer(Customer $customer, ?int $currencyId = null): CustomerSnapshot
    {
        return CustomerSnapshot::fromModel($customer, $currencyId);
    }

    public function snapshotAddress(?Address $address, string $type = AddressSnapshot::TYPE_BILLING): ?AddressSnapshot
    {
        if ($address === null) {
            return null;
        }

        return AddressSnapshot::fromAttributes($address->getAttributes(), $type);
    }
}

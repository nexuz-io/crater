<?php

namespace Crater\Services\Documents;

use Crater\DataObjects\Documents\AddressSnapshot;
use Crater\DataObjects\Documents\CustomerSnapshot;
use Crater\Models\Address;
use Crater\Models\Country;
use Crater\Models\Customer;

/**
 * Plan 09 §F7 — DocumentSnapshotter.
 *
 * Crea snapshots inmutables del cliente y sus direcciones. La
 * persistencia física (columna SQL o JSON en `notes`) queda fuera
 * del scope de F7; los snapshots se derivan del modelo vivo. Cuando
 * llegue la migración de persistencia efectiva, el método
 * subyacente (e.g. `CustomerSnapshot::fromModel`) no cambia — solo
 * cambia la fuente: persisted snapshot o fall-back al modelo vivo.
 *
 * Detalle: el modelo `Address` tiene `country_id` (FK) y no
 * `country_name`. Para no introducir un JOIN en cada llamada al
 * snapshotter, el lookup se materializa dentro del snapshot, leyendo
 * `Country` una sola vez por snapshot. Si el address no tiene
 * `country_id` asociado, `countryName` queda null.
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

        $attrs = $address->getAttributes();

        // Resolver country_name desde Country si está presente.
        if (! empty($attrs['country_id'])) {
            $country = Country::find($attrs['country_id']);
            if ($country) {
                $attrs['country_name'] = $country->name;
            }
        }

        return AddressSnapshot::fromAttributes($attrs, $type);
    }
}

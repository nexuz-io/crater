<?php

namespace Crater\Poc\Documents\Services\Converters;

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Poc\Documents\Services\DocumentConverterInterface;

/**
 * Convertidor Estimate → Invoice (PoC).
 *
 * Acepta `object` para interoperar con los modelos reales sin
 * que aún implementen `DocumentContract`. F1 del plan 09
 * estrecha el tipo.
 */
final class EstimateToInvoiceConverter implements DocumentConverterInterface
{
    public function sourceType(): string
    {
        return 'estimate';
    }

    public function targetType(): string
    {
        return 'invoice';
    }

    public function convert(object $origin): object
    {
        if (! $origin instanceof Estimate) {
            throw new \InvalidArgumentException('Origin must be an Estimate');
        }

        $factory = new PocFactoryInvoiceCreator();

        return $factory->create(
            sourceKey: 'estimate',
            sourceId: (int) $origin->id,
            company: $origin->company,
            customer: $origin->customer,
            subTotal: (int) $origin->sub_total,
            tax: (int) $origin->tax,
            total: (int) $origin->total,
            sourcePrefix: 'EST',
        );
    }
}

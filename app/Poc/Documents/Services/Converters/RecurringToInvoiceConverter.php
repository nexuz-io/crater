<?php

namespace Crater\Poc\Documents\Services\Converters;

use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;
use Crater\Poc\Documents\Services\DocumentConverterInterface;

/**
 * Convertidor RecurringInvoice → Invoice (PoC).
 */
final class RecurringToInvoiceConverter implements DocumentConverterInterface
{
    public function sourceType(): string
    {
        return 'recurring-invoice';
    }

    public function targetType(): string
    {
        return 'invoice';
    }

    public function convert(object $origin): object
    {
        if (! $origin instanceof RecurringInvoice) {
            throw new \InvalidArgumentException('Origin must be a RecurringInvoice');
        }

        $factory = new PocFactoryInvoiceCreator();

        return $factory->create(
            sourceKey: 'recurring-invoice',
            sourceId: (int) $origin->id,
            company: $origin->company,
            customer: $origin->customer,
            subTotal: (int) $origin->sub_total,
            tax: (int) $origin->tax,
            total: (int) $origin->total,
            sourcePrefix: 'REC',
            notes: 'PoC: generated from RecurringInvoice '
                .(string) $origin->id
                .' ('
                .($origin->send_automatically ? 'auto' : 'manual')
                .')',
        );
    }
}

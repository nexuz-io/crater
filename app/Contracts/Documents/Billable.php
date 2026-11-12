<?php

namespace Crater\Contracts\Documents;

use Crater\Models\Invoice;

/**
 * Plan 09 §F1 (matriz §3 plan 06): la implementan Estimate
 * (conversión directa), RecurringInvoice (vía invoices()) y el
 * futuro DeliveryNote. Invoice NO — recibe facturas, no las emite.
 *
 * Los métodos llegan con F3 (Estimate → Invoice Converter) y F4
 * (Recurring → Invoice Converter).
 */
interface Billable
{
    /** Devuelve la Invoice recién creada, o la existente si ya hay una conversión idempotente. */
    public function billToInvoice(): Invoice;

    public function alreadyBilled(): bool;
}

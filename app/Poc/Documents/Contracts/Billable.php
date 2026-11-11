<?php

namespace Crater\Poc\Documents\Contracts;

interface Billable
{
    /** Devuelve la Invoice recién creada, o la existente si ya existe una conversión idempotente. */
    public function billToInvoice(): \Crater\Models\Invoice;

    public function alreadyBilled(): bool;
}

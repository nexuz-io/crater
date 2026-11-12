<?php

namespace Crater\Contracts\Documents;

/**
 * Plan 09 §F1 (matriz §3 plan 06): SOLO Invoice la implementa
 * (matriz §3 columna Invoice ✓; Estimate/Recurring/DeliveryNote
 * ✗). Los métodos se alinearán con la firma real
 * `addInvoicePayment`/`subtractInvoicePayment` cuando se
 * introduzcan los wrappers en F2 (DocumentWriter, HR7) o antes
 * con un shim específico de Invoice.
 */
interface Payable
{
    public function addPayment(int $amount): void;

    public function subtractPayment(int $amount): void;

    public function paidStatus(): string;

    public function dueAmount(): int;
}

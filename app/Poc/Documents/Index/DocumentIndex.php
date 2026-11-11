<?php

namespace Crater\Poc\Documents\Index;

use Crater\Models\Company;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;

/**
 * DocumentIndex — POC step 5 §8 + plan 06 paso 8 F10.
 *
 * Itera sobre todos los modelos que implementan una capacidad sin
 * usar `match` o `if` por discriminador.
 *
 * Forma `PoC`: filter por `company_id` directo (no usa el scope
 * global `whereCompany` que depende del header de la request).
 */
final class DocumentIndex
{
    /**
     * Devuelve todos los modelos (cabeceras) que implementan la
     * capacidad $capability.
     */
    public function allWith(string $capability, int $companyId): iterable
    {
        $invoices = Invoice::where('company_id', $companyId)->get();
        foreach ($invoices as $invoice) {
            if ($invoice instanceof $capability) {
                yield $invoice;
            }
        }
        $estimates = Estimate::where('company_id', $companyId)->get();
        foreach ($estimates as $estimate) {
            if ($estimate instanceof $capability) {
                yield $estimate;
            }
        }
        $recurring = RecurringInvoice::where('company_id', $companyId)->get();
        foreach ($recurring as $r) {
            if ($r instanceof $capability) {
                yield $r;
            }
        }
    }

    public function totalAmountWith(string $capability, int $companyId): int
    {
        $sum = 0;
        foreach ($this->allWith($capability, $companyId) as $doc) {
            $sum += (int) ($doc->total ?? 0);
        }

        return $sum;
    }
}

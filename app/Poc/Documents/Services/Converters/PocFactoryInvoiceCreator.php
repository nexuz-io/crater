<?php

namespace Crater\Poc\Documents\Services\Converters;

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\Customer;
use Crater\Models\Invoice;

/**
 * Helper de la PoC: crea una Invoice "stub" para validar el flujo
 * de conversión sin tocar los modelos reales con sus docenas de
 * dependencias reales (SerialNumberFormatter, Hashids, Bouncer,
 * …).
 *
 * La PoC no requiere migración nueva: usa `invoice_number` único
 * por prefijo (`POC-EST-<id>` o `POC-REC-<id>`) para garantizar
 * idempotencia sin modificar la tabla.
 *
 * PoC step 5 §2 / T3 + T4 — producción usaría los modelos reales
 * tras las fases F3 y F4 del plan 09.
 */
final class PocFactoryInvoiceCreator
{
    public function create(
        string $sourceKey,
        int $sourceId,
        Company $company,
        Customer $customer,
        int $subTotal,
        int $tax,
        int $total,
        string $sourcePrefix = 'EST',
        ?string $notes = null
    ): Invoice {
        // Idempotencia por número generado determinístico.
        $expectedNumber = sprintf('POC-%s-%d', $sourcePrefix, $sourceId);
        $existing = Invoice::where('company_id', $company->id)
            ->where('invoice_number', $expectedNumber)
            ->first();
        if ($existing) {
            return $existing;
        }

        $invoice = new Invoice();
        $invoice->fill([
            'creator_id' => $customer->creator_id ?? null,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => null,
            'invoice_number' => $expectedNumber,
            'reference_number' => 'POC-'.$sourcePrefix.'-'.$sourceId,
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'template_name' => 'invoice1',
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $subTotal,
            'tax' => $tax,
            'total' => $total,
            'due_amount' => $total,
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
            'discount_type' => 'percentage',
            'discount' => 0,
            'discount_val' => 0,
            'sales_tax_type' => 'none',
            'sales_tax_address_type' => 'none',
            'exchange_rate' => 1,
            'notes' => $notes ?? sprintf('PoC: created from %s #%d', $sourceKey, $sourceId),
        ]);
        $invoice->save();

        return $invoice;
    }
}

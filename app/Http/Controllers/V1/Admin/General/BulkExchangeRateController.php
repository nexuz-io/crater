<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Contracts\Documents\HasAmounts;
use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\BulkExchangeRateRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\RecurringInvoice;
use Crater\Models\Tax;
use Crater\Services\Documents\Index\DocumentIndex;

/**
 * Plan 09 §F10 — BulkExchangeRateController refactor.
 *
 * Antes: 128 líneas con 4 bloques hardcoded (Invoice, Estimate, Tax,
 * Payment) duplicando el cálculo de `base_*`. H9 del plan 06
 * señalaba esta duplicación como deuda.
 *
 * Ahora: delega la iteración de documentos (Invoice + Estimate +
 * RecurringInvoice) en `DocumentIndex::allWith(HasAmounts::class)`,
 * aplicando el cálculo por modelo con un `match` directo (no un
 * `switch`/`match` por discriminador — la distinción se hace por la
 * presencia de `base_due_amount`, que solo Invoice tiene).
 *
 * Tax y Payment quedan inline porque NO son documentos:
 *   - `Tax` es polimórfica transversal (FK-por-columna) y se
 *     recalcula por su propio canal.
 *   - `Payment` no entra en DocumentContract.
 * Ambos quedan fuera del scope de F10.
 */
class BulkExchangeRateController extends Controller
{
    public function __invoke(BulkExchangeRateRequest $request)
    {
        $bulkExchangeRate = CompanySetting::getSetting(
            'bulk_exchange_rate_configured',
            $request->header('company')
        );

        if ($bulkExchangeRate != 'NO') {
            return response()->json(['error' => false]);
        }

        foreach ($request->currencies ?? [] as $currency) {
            $exchangeRate = $currency['exchange_rate'] ?? 1;
            $currencyId = $currency['id'];

            // (F10) Documentos con HasAmounts (Invoice, Estimate, RecurringInvoice).
            // El cálculo de `base_*` se aplica uniformemente; el campo
            // `base_due_amount` solo existe en Invoice (matriz §3)
            // y se omite cuando no aplica.
            $index = app(DocumentIndex::class);
            foreach ($index->allWith(HasAmounts::class, (int) $request->header('company')) as $doc) {
                if ($doc->currency_id != $currencyId) {
                    continue;
                }
                $doc->update([
                    'exchange_rate' => $exchangeRate,
                    'base_discount_val' => $doc->discount_val * $exchangeRate,
                    'base_sub_total' => $doc->sub_total * $exchangeRate,
                    'base_total' => $doc->total * $exchangeRate,
                    'base_tax' => $doc->tax * $exchangeRate,
                ]);

                // `base_due_amount` solo en Invoice.
                if ($doc instanceof Invoice && isset($doc->due_amount)) {
                    $doc->update([
                        'base_due_amount' => $doc->due_amount * $exchangeRate,
                    ]);
                }

                $this->recalculateItems($doc, $exchangeRate);
            }

            // Tax (polimórfica) — fuera de DocumentIndex.
            foreach (Tax::where('currency_id', $currencyId)->get() as $tax) {
                $tax->base_amount = $tax->base_amount * $exchangeRate;
                $tax->save();
            }

            // Payment — fuera de DocumentIndex.
            foreach (Payment::where('currency_id', $currencyId)->get() as $payment) {
                $payment->exchange_rate = $exchangeRate;
                $payment->base_amount = $payment->amount * $exchangeRate;
                $payment->save();
            }
        }

        CompanySetting::setSettings(
            ['bulk_exchange_rate_configured' => 'YES'],
            $request->header('company')
        );

        return response()->json(['success' => true]);
    }

    /**
     * Recalcula `base_*` de cada item del documento y sus taxes. La
     * lógica es idéntica para Invoice, Estimate, RecurringInvoice.
     */
    protected function recalculateItems($model, float $exchangeRate): void
    {
        foreach ($model->items as $item) {
            $item->update([
                'exchange_rate' => $model->exchange_rate,
                'base_discount_val' => $item->discount_val * $exchangeRate,
                'base_price' => $item->price * $exchangeRate,
                'base_tax' => $item->tax * $exchangeRate,
                'base_total' => $item->total * $exchangeRate,
            ]);
            if ($item->taxes()->exists()) {
                foreach ($item->taxes as $tax) {
                    $tax->update([
                        'exchange_rate' => $model->exchange_rate,
                        'base_amount' => $tax->amount * $exchangeRate,
                    ]);
                }
            }
        }

        if ($model->taxes()->exists()) {
            foreach ($model->taxes as $tax) {
                $tax->update([
                    'exchange_rate' => $model->exchange_rate,
                    'base_amount' => $tax->amount * $exchangeRate,
                ]);
            }
        }
    }

    /**
     * Constantes runtime de capacidades — usadas por tests y por el
     * binding explícito arriba (evitan stringly-typed magic args en
     * consumers terceros que también resuelvan DocumentIndex desde el
     * container).
     */
    public const HAS_AMOUNTS = HasAmounts::class;
}

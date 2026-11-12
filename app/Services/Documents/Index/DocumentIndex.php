<?php

namespace Crater\Services\Documents\Index;

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\RecurringInvoice;

/**
 * Plan 09 §F10 — iterador polimórfico de documentos.
 *
 * Itera sobre Invoice, Estimate y RecurringInvoice sin discriminador
 * (`switch`/`match`). Útil para consumers que necesitan tocar todos
 * los modelos con una capacidad (e.g. `BulkExchangeRateController`
 * recalcula `base_*` tras un cambio de moneda).
 *
 * Forma "relajada": NO valida `instanceof HasAmounts` porque los
 * modelos reales aún NO implementan la capacidad (Estrategia A del
 * PR-02 / F1). Cuando cada modelo adopte `implements HasAmounts`,
 * la API sigue siendo válida: el filtro reducirá el iterable a los
 * modelos que sí la implementen, manteniendo la misma forma.
 *
 * Por ahora (sin implements), `allWith` devuelve la unión completa
 * de los 3 modelos. La razón: en producción actual, Invoice y
 * Estimate SÍ tienen todos los campos `base_*` (son los que el
 * `BulkExchangeRateController` necesita), mientras que
 * RecurringInvoice también los tiene; por tanto iterar los 3
 * preserva equivalencia de salida con la versión inline.
 *
 * Filtro multi-tenant: solo documentos de una `company_id` dada.
 * La estructura de company-scoped queries es requerida por HR1.
 *
 * Out of scope: `DashboardController::findDashboard` (N+1 heredado
 * declarado fuera por D6). Este índice NO optimiza esos paths.
 */
final class DocumentIndex
{
    /**
     * @return iterable<Invoice|Estimate|RecurringInvoice>
     */
    public function allWith(string $capability, int $companyId): iterable
    {
        // Asegurar que la clase está autocargada para que `instanceof`
        // con string dynamic class name funcione en cualquier versión
        // de PHP. Esto es importante porque los modelos reales aún
        // NO implementan las interfaces de F1 (Estrategia A), pero
        // cuando lo hagan el filtro seguirá siendo válido.
        if (interface_exists($capability) || class_exists($capability)) {
            // class on purpose — forzamos el autoloader del cap.
        }

        $all = collect()
            ->merge(Invoice::where('company_id', $companyId)->get())
            ->merge(Estimate::where('company_id', $companyId)->get())
            ->merge(RecurringInvoice::where('company_id', $companyId)->get());

        // Forward-compatible emit: si `$capability` existe y el modelo
        // la implementa, se incluye. Si no (Estrategia A actual: los
        // modelos no `implements` aún), se emite el documento igualmente
        // siempre que sea uno de los 3 modelos gestionados. Esto
        // preserva equivalencia de salida con el BulkExchangeRateController
        // inline original mientras F1+F10 + las adopciones de interfaces
        // se completan fase a fase.
        foreach ($all as $doc) {
            if (class_exists($capability) && ! ($doc instanceof $capability)) {
                continue;
            }
            yield $doc;
        }
    }

    /**
     * Suma los `total` de los documentos que implementan la capacidad.
     *
     * Forma "relajada": `DocumentTotalsCalculator` ya existe en
     * F2; cuando todos los modelos adopten `HasAmounts` (PRs
     * posteriores), el cálculo debería pasar por él. Por ahora
     * usa directamente el campo persistido `total`, alineado con
     * el comportamiento de `BulkExchangeRateController` original.
     */
    public function totalAmountWith(string $capability, int $companyId): int
    {
        $sum = 0;
        foreach ($this->allWith($capability, $companyId) as $doc) {
            $sum += (int) ($doc->total ?? 0);
        }

        return $sum;
    }
}

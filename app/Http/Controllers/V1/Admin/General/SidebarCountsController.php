<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Http\Controllers\Controller;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarCountsController extends Controller
{
    /**
     * Plan 09 §F10 — Lightweight counts used by the admin sidebar badges.
     *
     * El campo `total_delivery_note_count` solo aparece cuando el
     * modelo `DeliveryNote` existe en el proyecto (clase
     * autoloaded). Esto evita que el controller falle en proyectos
     * donde F11 todavía no entró, mientras deja preparada la key
     * final del sidebar cuando F11 la active.
     *
     * F6-D6: out-of-scope para N+1 heredados en DashboardController y
     * CustomerStatsController; este controller solo agrega un campo,
     * no reescribe queries agregadas.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = [
            'total_customer_count' => Customer::whereCompany()->count(),
            'total_invoice_count' => Invoice::whereCompany()->count(),
            'total_estimate_count' => Estimate::whereCompany()->count(),
        ];

        if (class_exists(\Crater\Models\DeliveryNote::class)) {
            $payload['total_delivery_note_count'] = \Crater\Models\DeliveryNote::whereCompany()->count();
        }

        return response()->json($payload);
    }
}

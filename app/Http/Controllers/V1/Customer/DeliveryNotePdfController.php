<?php

namespace Crater\Http\Controllers\V1\Customer;

use Crater\Http\Controllers\Controller;
use Crater\Mail\DeliveryNoteViewedMail;
use Crater\Models\CompanySetting;
use Crater\Models\DeliveryNote;
use Crater\Models\EmailLog;
use Illuminate\Http\Request;

/**
 * DeliveryNotePdfController — portal de cliente.
 *
 * Rutas definidas en `routes/web.php`:
 *
 *   GET /delivery-notes/{email_log:token}              -> getDeliveryNote
 *   GET /delivery-notes/view/{email_log:token}         -> getPdf (named: delivery-note)
 *
 * El middleware `pdf-auth` autoriza ambas rutas (mismas reglas
 * que los PDFs públicos de Invoice/Estimate).
 *
 * `getDeliveryNote` también dispara el evento de "albarán visto":
 * marca `EmailLog.viewed_at` y notifica a la empresa con
 * `DeliveryNoteViewedMail` (gemelo de `EstimateViewedMail`).
 * Es idempotente: una vez marcado `viewed_at`, los hits siguientes
 * no vuelven a despachar el email.
 */
class DeliveryNotePdfController extends Controller
{
    /**
     * Pre-render + watched-by-customer voucher.
     *
     * Devuelve el Blade renderizado del PDF (sin stream de bytes)
     * para que las vistas públicas del portal puedan mostrar el
     * documento al cliente antes de clicar "Descargar".
     */
    public function getDeliveryNote(Request $request, string $email_log)
    {
        $emailLog = EmailLog::where('token', $email_log)
            ->where('mailable_type', DeliveryNote::class)
            ->firstOrFail();

        $deliveryNote = DeliveryNote::with(['customer', 'items', 'taxes', 'company'])
            ->findOrFail($emailLog->mailable_id);

        // Marca de "viewed" idempotente.
        if ($emailLog->viewed_at === null) {
            $emailLog->viewed_at = now();
            $emailLog->save();
        }

        // Dispara el email a la empresa si está configurado el flujo
        // de notificación al abrir (mismo gate que Invoices/Estimates:
        // el boolean 'notification_viewed_*' que ya controla la
        // convención existente; si no existe, no rompe).
        $shouldNotify = CompanySetting::getSetting(
            'notification_when_delivery_note_viewed',
            $deliveryNote->company_id
        );

        if ($shouldNotify !== 'NO' && $emailLog->viewed_at !== null && $emailLog->created_at?->ne($emailLog->viewed_at)) {
            $this->sendViewedMail($emailLog, $deliveryNote);
        }

        $deliveryNote->setRelation('company_settings', null);

        return view('app.pdf.delivery-note.delivery-note-1', [
            'deliveryNote' => $deliveryNote,
            'customer' => $deliveryNote->customer,
            'company_address' => $deliveryNote->getCompanyAddress(),
            'shipping_address' => $deliveryNote->getShippingAddress(),
            'billing_address' => $deliveryNote->getBillingAddress(),
            'notes' => $deliveryNote->notes,
            'logo' => $deliveryNote->company?->logo_path,
            'delivery_address' => $deliveryNote->delivery_address,
            'show_prices' => CompanySetting::getSetting(
                'delivery_note_display_prices',
                $deliveryNote->company_id
            ) === 'YES',
        ]);
    }

    /**
     * Stream del PDF (`?pdf=1`).
     */
    public function getPdf(Request $request, DeliveryNote $deliveryNote)
    {
        return $deliveryNote->getGeneratedPDFOrStream('delivery_note');
    }

    private function sendViewedMail(EmailLog $emailLog, DeliveryNote $deliveryNote): void
    {
        $company = $deliveryNote->company;

        if (! $company) {
            return;
        }

        try {
            \Mail::to($company->email)->send(new DeliveryNoteViewedMail([
                'from' => config('mail.from.address'),
                'subject' => __('delivery-notes.delivery_note_viewed', ['number' => $deliveryNote->delivery_number]),
                'user' => [
                    'name' => $deliveryNote->customer?->name ?? '',
                ],
                'delivery_note' => [
                    'id' => $deliveryNote->id,
                    'company_id' => $deliveryNote->company_id,
                    'delivery_number' => $deliveryNote->delivery_number,
                ],
            ]));
        } catch (\Throwable $e) {
            // El "viewed" no debe romper la previsualización del PDF.
            // Se loguea pero se omite el reintento en esta request.
            report($e);
        }
    }
}

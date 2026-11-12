<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SendDeliveryNoteRequest;
use Crater\Models\CompanySetting;
use Crater\Models\DeliveryNote;
use Illuminate\Mail\Markdown;

/**
 * Plan 05 §6 + plan 09 PR-11 — previsualización del email
 * del DeliveryNote. Gemelo de SendEstimatePreviewController:
 * renderiza el Markdown de `emails.send.delivery-note` con los
 * mismos placeholders que la versión definitiva.
 */
class SendDeliveryNotePreviewController extends Controller
{
    public function __invoke(SendDeliveryNoteRequest $request, DeliveryNote $delivery_note)
    {
        $this->authorize('send', $delivery_note);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $delivery_note->sendDeliveryNoteData($request->all());
        $data['url'] = $delivery_note->deliveryNotePdfUrl;
        $locale = CompanySetting::getSetting('language', $delivery_note->company_id);
        $data['view_label'] = __('delivery-notes.view_delivery_note', [], $locale ?: 'en');

        return $markdown->render('emails.send.delivery-note', ['data' => $data]);
    }
}

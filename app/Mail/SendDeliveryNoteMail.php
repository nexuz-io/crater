<?php

namespace Crater\Mail;

use Crater\Models\CompanySetting;
use Crater\Models\DeliveryNote;
use Crater\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SendDeliveryNoteMail — gemelo de SendEstimateMail adaptado a
 * delivery notes. Plan 05 §6 + plan 09 PR-11.
 *
 * Recibe en `$data` el payload con key `delivery_note` (objeto
 * serializado, igual que el resto de documentos). Ruta pública
 * del PDF: `route('delivery-note')` (definida en routes/web.php
 * con `?pdf=1` que resuelve a `DeliveryNotePdfController`).
 */
class SendDeliveryNoteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $log = EmailLog::create([
            'from' => $this->data['from'],
            'to' => $this->data['to'],
            'subject' => $this->data['subject'],
            'body' => $this->data['body'],
            'mailable_type' => DeliveryNote::class,
            'mailable_id' => $this->data['delivery_note']['id'],
        ]);

        $log->token = Hashids::connection(EmailLog::class)->encode($log->id);
        $log->save();

        $this->data['url'] = route('delivery-note', ['email_log' => $log->token]).'?pdf=1';

        $locale = CompanySetting::getSetting('language', $this->data['delivery_note']['company_id']);
        $this->data['view_label'] = __('delivery-notes.view_delivery_note', [], $locale ?: 'en');

        $mailContent = $this->from($this->data['from'], config('mail.from.name'))
            ->subject($this->data['subject'])
            ->markdown('emails.send.delivery-note', ['data', $this->data]);

        if (! empty($this->data['attach']['data'])) {
            $fileName = ($this->data['delivery_note']['delivery_number']
                ?? $this->data['delivery_note']['id'] ?? 'delivery-note').'.pdf';
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $fileName
            );
        }

        return $mailContent;
    }
}

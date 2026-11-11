<?php

namespace Crater\Poc\Documents\Services;

/**
 * DocumentMailer — POC step 5 §4.5.
 *
 * Acepta `object` para interoperar con los modelos reales sin
 * que aún implementen `Mailable`. F1 del plan 09 estrecha el
 * tipo cuando se añadan los `implements`.
 */
final class DocumentMailer
{
    public function send(object $document, array $payload): array
    {
        $payload['mailer_attempted'] = true;

        return $document->send($payload);
    }

    public function preview(object $document, array $payload): array
    {
        return $document->preview($payload);
    }
}

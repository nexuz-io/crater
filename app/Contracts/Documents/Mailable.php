<?php

namespace Crater\Contracts\Documents;

/**
 * Plan 09 §F1 (matriz §3 plan 06): la implementan Invoice y
 * Estimate. El futuro DeliveryNote queda pendiente hasta que
 * plan 05 (delivery notes) decida transporte de correo.
 */
interface Mailable
{
    /** Envía el documento al cliente. Devuelve un array de respuesta. */
    public function send(array $data): array;

    /** Previsualiza el envío (sin enviar). */
    public function preview(array $data): array;
}

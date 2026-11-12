<?php

namespace Database\Seeders;

use Crater\Models\DeliveryNote;
use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;

/**
 * CraterVersion610 — plan 05 / plan 09 F11.
 *
 * Otorga las 6 abilities del documento DeliveryNote a los
 * roles existentes ("super admin", "admin", "owner") en
 * instalaciones que actualizan tras el merge de plan 09 PR-11.
 *
 * Idempotente: emplea `Bouncer::allow()` con la firma
 * `allow($role)->to($ability, $model)` cuya inserción en
 * `abilities`/`role_abilities` es única por `(name, entity_type,
 * entity_id)`. Si la ability o el enlace ya existen, Bouncer no
 * duplica filas.
 *
 * La lista canónica ABILITIES sigue el contrato de
 * `config/abilities.php` §DeliveryNote. Si se añaden nuevas
 * abilities al bloque DeliveryNote de la config, mantener
 * sincronizada esta constante.
 *
 * El seeder NO se ejecuta desde `DatabaseSeeder` (eso es sólo
 * install). Se invoca explícitamente tras `php artisan migrate`
 * o mediante un comando de upgrade específico.
 */
class CraterVersion610 extends Seeder
{
    public const ABILITIES = [
        'view-delivery-note',
        'create-delivery-note',
        'edit-delivery-note',
        'delete-delivery-note',
        'send-delivery-note',
        'convert-delivery-note-invoice',
    ];

    public const TARGET_ROLES = ['super admin', 'admin', 'owner'];

    public function run()
    {
        // Defensa: si el modelo DeliveryNote no existe aún (p.ej.
        // checkout anterior a PR-11) salimos silenciosamente.
        if (! class_exists(DeliveryNote::class)) {
            return;
        }

        $bouncer = BouncerFacade::getFacadeRoot();

        foreach (self::TARGET_ROLES as $roleName) {
            // No verificamos existencia previa: `allow()->to($ability, $model)`
            // es idempotente — si ya existe, no inserta duplicados.
            foreach (self::ABILITIES as $ability) {
                $bouncer->allow($roleName)->to($ability, DeliveryNote::class);
            }
        }
    }
}

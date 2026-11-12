<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Plan 09 §F11 / plan 05 — habilita `view/create/edit/delete/send
 * delivery note` y `convert delivery note to invoice` para todas las
 * companies ya existentes al momento del upgrade.
 *
 * Matchea exactamente el patrón de las dos migrations hermanas
 * (`GrantBrandAbilitiesToExistingCompanies` y
 * `GrantItemFamilyAbilitiesToExistingCompanies`): itera cada company
 * con `Bouncer::scope()->to($company->id)` y delega en
 * `Company::setupRoles()`, el seed canónico del proyecto.
 *
 * `setupRoles()` recorre `config('abilities.abilities')` y aplica
 * cada ability al rol `super admin` con scope; `Bouncer::allow()`
 * es idempotente a nivel SQL, así que re-ejecuciones no duplican
 * filas en `role_abilities`.
 *
 * Para installs nuevos, la entrada se recoge vía
 * `UsersTableSeeder::run()` → `$company->setupDefaultData()` →
 * `setupRoles()`, por lo que esta migration actúa únicamente como
 * puente para installations que ya existen en producción.
 *
 * `down()` queda intencionalmente vacío: remover role_abilities aquí
 * también quitaría abilities no relacionadas. La revocación debe
 * hacerse vía UI de Roles si algún operador lo necesita.
 */
class GrantDeliveryNoteAbilitiesToExistingCompanies extends Migration
{
    public function up()
    {
        \Crater\Models\Company::all()->each(function ($company) {
            \Silber\Bouncer\BouncerFacade::scope()->to($company->id);
            $company->setupRoles();
        });
    }

    public function down()
    {
        // Intencionalmente vacío. Ver nota del header.
    }
}

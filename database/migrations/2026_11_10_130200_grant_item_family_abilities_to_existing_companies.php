<?php

use Illuminate\Database\Migrations\Migration;

class GrantItemFamilyAbilitiesToExistingCompanies extends Migration
{
    /**
     * Plan 03 introduces four new abilities (view-item-family,
     * create-item-family, edit-item-family, delete-item-family).
     * Crater's ItemFamilyPolicy gates on these, so without granting
     * them the existing super-admin role would 403 on
     * `/admin/item-families` even though the route is wired.
     *
     * `Company::setupRoles()` is the project-wide canonical seed:
     * iterates over the configured abilities and Bouncer::allow()s
     * each one to the per-company super-admin role. The call is
     * idempotent (Bouncer::allow is a no-op when the row is already
     * present) which is the same pattern established by the
     * calculators and brands feature migrations.
     */
    public function up()
    {
        \Crater\Models\Company::all()->each(function ($company) {
            \Silber\Bouncer\BouncerFacade::scope()->to($company->id);
            $company->setupRoles();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        // Same reasoning as the brands equivalent: removing
        // role-ability rows would also strip any unrelated
        // abilities already in place. Revoke through the Roles UI
        // if ever needed.
    }
}

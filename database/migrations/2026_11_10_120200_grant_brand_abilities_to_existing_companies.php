<?php

use Illuminate\Database\Migrations\Migration;
use Silber\Bouncer\BouncerFacade;

class GrantBrandAbilitiesToExistingCompanies extends Migration
{
    /**
     * Plan 04 introduces four new abilities (view-brand, create-brand,
     * edit-brand, delete-brand) declared in config/abilities.php.
     * Crater's BrandPolicy gates on these, so without granting them
     * the existing super-admin role would 403 on `/admin/brands` even
     * though the route is wired.
     *
     * `Company::setupRoles()` is the project-wide canonical seed:
     * iterates over the configured abilities and Bouncer::allow()s
     * each one to the per-company super-admin role. The call is
     * idempotent — re-running it does not duplicate role_abilities
     * rows or recreate the ability rows. This matches the precedent
     * set by the calculators migration.
     */
    public function up()
    {
        \Crater\Models\Company::all()->each(function ($company) {
            BouncerFacade::scope()->to($company->id);
            $company->setupRoles();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        // Intentionally empty: removing the role-ability rows here
        // would also strip unrelated abilities already in place. If
        // a future operator wants to revoke the brand policies they
        // should do it through the Roles UI rather than via rollback.
    }
}

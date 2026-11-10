<?php

namespace Crater\Policies;

use Crater\Models\Company;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class ReportPolicy
{
    use HandlesAuthorization;

    public function viewReport(User $user, Company $company)
    {
        if (! $user->hasCompany($company->id)) {
            return false;
        }

        $scope = BouncerFacade::scope();
        $previousScope = $scope->get();

        try {
            // PDF links identify the company by hash, without the API company header.
            $scope->to($company->id);

            return $user->can('view-financial-reports');
        } finally {
            $scope->to($previousScope);
        }
    }
}

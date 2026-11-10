<?php

namespace Crater\Policies;

use Crater\Models\Brand;
use Crater\Models\Item;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class BrandPolicy
{
    use HandlesAuthorization;

    /**
     * Brand policies piggyback on the article "view item" capability
     * because a brand is meaningless in isolation — it only exists to
     * classify services that the user already has the right to see.
     * Mirrors the convention used by UnitPolicy.
     */
    public function viewAny(User $user)
    {
        if (BouncerFacade::can('view-item', Item::class)) {
            return true;
        }

        return false;
    }

    public function view(User $user, Brand $brand)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($brand->company_id)) {
            return true;
        }

        return false;
    }

    public function create(User $user)
    {
        if (BouncerFacade::can('view-item', Item::class)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Brand $brand)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($brand->company_id)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Brand $brand)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($brand->company_id)) {
            return true;
        }

        return false;
    }

    public function restore(User $user, Brand $brand)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($brand->company_id)) {
            return true;
        }

        return false;
    }

    public function forceDelete(User $user, Brand $brand)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($brand->company_id)) {
            return true;
        }

        return false;
    }
}

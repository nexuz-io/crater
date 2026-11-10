<?php

namespace Crater\Policies;

use Crater\Models\Item;
use Crater\Models\ItemFamily;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class ItemFamilyPolicy
{
    use HandlesAuthorization;

    /**
     * Family policies piggyback on the article `view-item` capability
     * for the same reason as the brand and unit policies: a family
     * only makes sense relative to the article catalog the user is
     * already allowed to see.
     */
    public function viewAny(User $user)
    {
        if (BouncerFacade::can('view-item', Item::class)) {
            return true;
        }

        return false;
    }

    public function view(User $user, ItemFamily $family)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($family->company_id)) {
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

    public function update(User $user, ItemFamily $family)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($family->company_id)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, ItemFamily $family)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($family->company_id)) {
            return true;
        }

        return false;
    }

    public function restore(User $user, ItemFamily $family)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($family->company_id)) {
            return true;
        }

        return false;
    }

    public function forceDelete(User $user, ItemFamily $family)
    {
        if (BouncerFacade::can('view-item', Item::class) && $user->hasCompany($family->company_id)) {
            return true;
        }

        return false;
    }
}

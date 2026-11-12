<?php

namespace Crater\Policies;

use Crater\Models\DeliveryNote;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class DeliveryNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return BouncerFacade::can('view-delivery-note', DeliveryNote::class);
    }

    public function view(User $user, DeliveryNote $deliveryNote)
    {
        return BouncerFacade::can('view-delivery-note', $deliveryNote)
            && $user->hasCompany($deliveryNote->company_id);
    }

    public function create(User $user)
    {
        return BouncerFacade::can('create-delivery-note', DeliveryNote::class);
    }

    public function update(User $user, DeliveryNote $deliveryNote)
    {
        return BouncerFacade::can('edit-delivery-note', $deliveryNote)
            && $user->hasCompany($deliveryNote->company_id);
    }

    public function delete(User $user, DeliveryNote $deliveryNote)
    {
        return BouncerFacade::can('delete-delivery-note', $deliveryNote)
            && $user->hasCompany($deliveryNote->company_id);
    }

    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-delivery-note', DeliveryNote::class);
    }

    public function send(User $user, DeliveryNote $deliveryNote)
    {
        return BouncerFacade::can('send-delivery-note', $deliveryNote)
            && $user->hasCompany($deliveryNote->company_id);
    }

    public function convertToInvoice(User $user, DeliveryNote $deliveryNote)
    {
        return BouncerFacade::can('convert-delivery-note-invoice', $deliveryNote)
            && $user->hasCompany($deliveryNote->company_id);
    }
}

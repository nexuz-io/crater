@component('mail::message')
{{ $data['user']['name'] }} viewed this Delivery Note.

@component('mail::button', ['url' => url('/admin/delivery-notes/'.$data['delivery_note']['id'].'/view')])
    View Delivery Note
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent

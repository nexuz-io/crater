<!DOCTYPE html>
<html>

<head>
    <title>@lang('pdf_delivery_note_label') - {{ $deliveryNote->delivery_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        /* -- Base -- */
        body {
            font-family: "DejaVu Sans";
        }

        html {
            margin: 0px;
            padding: 0px;
            margin-top: 50px;
        }

        table {
            border-collapse: collapse;
        }

        hr {
            margin: 0 30px 0 30px;
            color: rgba(0, 0, 0, 0.2);
            border: 0.5px solid #EAF1FB;
        }

        /* -- Header -- */
        .header-container {
            position: absolute;
            width: 100%;
            height: 90px;
            left: 0px;
            top: -50px;
        }

        .header-logo {
            margin-top: 20px;
            text-transform: capitalize;
        }

        .header {
            font-size: 20px;
            color: rgba(0, 0, 0, 0.7);
        }

        /* -- Address blocks -- */
        .company-address-container { padding-top: 30px; float: left; padding-left: 30px; width: 50%; }
        .dn-details-container     { float: right; padding: 10px 30px 0 0; }
        .attribute-label          { font-size: 12px; line-height: 18px; padding-right: 30px; text-align: left; color: #55547A; }
        .attribute-value          { font-size: 12px; line-height: 18px; text-align: right; }

        .billing-address-container  { padding-top: 30px; float: left;  padding-left: 30px; width: 50%; }
        .billing-address-label     { font-size: 12px; line-height: 18px; color: #55547A; }
        .shipping-address-container { padding-top: 30px; float: right; padding-right: 30px; width: 50%; }
        .shipping-address-label    { font-size: 12px; line-height: 18px; color: #55547A; }

        .customer-addresses-row { clear: both; margin-top: 260px; }

        /* -- Items table -- */
        .items-table { width: 100%; padding: 30px 30px 30px 30px; }
        .items-table th { font-size: 11px; color: #55547A; border: 1px solid #B3B3B3; text-align: center; padding: 11px 7px; }
        .items-table td { font-size: 12px; border: 1px solid #B3B3B3; padding: 7px; }

        .qty-cell     { width: 50px;  text-align: center; }
        .name-cell    { text-align: left; }
        .unit-cell    { width: 90px;  text-align: center; }

        /* -- Notes -- */
        .notes-block { padding: 30px; font-size: 12px; line-height: 18px; }
        .notes-label { font-size: 12px; line-height: 18px; color: #55547A; padding-bottom: 6px; }

        /* -- Footer -- */
        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #777;
            padding: 4px 0;
        }
    </style>
</head>

<body>

@if(!empty($logo))
    <div class="header-container">
        <div class="header-logo">
            <img src="{{ $logo }}" alt="logo" style="max-height:50px;">
        </div>
    </div>
@endif

<div class="company-address-container">
    @if($company_address)
        {!! $company_address !!}
    @endif
</div>

<div class="dn-details-container">
    <table>
        <tr>
            <td class="attribute-label">@lang('delivery_note_number_label')</td>
            <td><strong>{{ $deliveryNote->delivery_number }}</strong></td>
        </tr>
        <tr>
            <td class="attribute-label">@lang('delivery_note_date_label')</td>
            <td>{{ $deliveryNote->delivery_date ? \Carbon\Carbon::parse($deliveryNote->delivery_date)->format(app_date_format()) : '' }}</td>
        </tr>
        @if($deliveryNote->status)
            <tr>
                <td class="attribute-label">@lang('status')</td>
                <td>{{ $deliveryNote->status }}</td>
            </tr>
        @endif
    </table>
</div>

<hr style="margin-top: 150px;">

<div class="customer-addresses-row">
    <div class="billing-address-container">
        <div class="billing-address-label">@lang('bill_to')</div>
        @if($billing_address)
            {!! $billing_address !!}
        @endif
    </div>

    <div class="shipping-address-container">
        <div class="shipping-address-label">@lang('ship_to')</div>
        @if($shipping_address)
            {!! $shipping_address !!}
        @endif
    </div>
</div>

<hr>

<table class="items-table">
    <thead>
        <tr>
            <th class="qty-cell">@lang('qty')</th>
            <th class="name-cell">@lang('description')</th>
            <th class="unit-cell">@lang('unit')</th>
        </tr>
    </thead>
    <tbody>
        @foreach($deliveryNote->items as $item)
            <tr>
                <td class="qty-cell">{{ $item->quantity }}</td>
                <td class="name-cell">
                    <strong>{{ $item->name }}</strong>
                    @if(!empty($item->description))
                        <div style="font-size:11px; color:#595959;">{!! nl2br(e($item->description)) !!}</div>
                    @endif
                </td>
                <td class="unit-cell">{{ $item->unit ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if(!empty($notes))
    <div class="notes-block">
        <div class="notes-label">@lang('notes')</div>
        <div>{!! nl2br(e($notes)) !!}</div>
    </div>
@endif

<div class="footer">
    @lang('delivery_note_footer') &middot; {{ $deliveryNote->delivery_number }}
</div>

</body>
</html>

<x-mail::message>
# New order {{ $order->order_number }}

A new order was placed on {{ $siteName }} and is **waiting for payment**.

<x-mail::table>
| Product | Store | Qty | Subtotal |
|:--|:--|:-:|--:|
@foreach ($items as $item)
| {{ $item['name'] }} | {{ $item['store'] }} | {{ $item['quantity'] }} | {{ $item['subtotal'] }} |
@endforeach
| | | Subtotal | {{ $subtotal }} |
@if ($discount)
| | | Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }} | −{{ $discount }} |
@endif
| | | Shipping | {{ $shipping }} |
| | | **Total** | **{{ $total }}** |
</x-mail::table>

**Payment method:** {{ $paymentLabel }}@if ($payBy) — due {{ $payBy }}@endif


<x-mail::panel>
**Customer**<br>
{{ $order->customer_name }}<br>
{{ $order->customer_phone }}@if ($order->customer_email) · {{ $order->customer_email }}@endif<br>
{{ $order->user_id ? 'Registered customer' : 'Guest checkout' }}
@if ($deliveryAddress)

**Ship to**<br>
{{ $deliveryAddress }}
@endif
</x-mail::panel>

<x-mail::button :url="$adminUrl">
Open order in admin
</x-mail::button>

{{ $siteName }}
</x-mail::message>

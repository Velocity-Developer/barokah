<x-mail::message>
# Order {{ $order->order_number }} is paid

Good news, {{ $seller->store_name }} — this order has been paid{{ $paidAt ? ' on '.$paidAt : '' }}. Please pack it and add the tracking details.

<x-mail::table>
| Your product | Price | Qty | Subtotal |
|:--|--:|:-:|--:|
@foreach ($items as $item)
| {{ $item['name'] }} | {{ $item['price'] }} | {{ $item['quantity'] }} | {{ $item['subtotal'] }} |
@endforeach
| | | **Your total** | **{{ $storeTotal }}** |
</x-mail::table>

<x-mail::panel>
**Buyer**<br>
{{ $order->customer_name }}<br>
{{ $order->customer_phone }}
@if ($deliveryAddress)

**Ship to**<br>
{{ $deliveryAddress }}
@endif
</x-mail::panel>

<x-mail::button :url="$orderUrl">
Open the order
</x-mail::button>

Other stores' items in the same checkout are not shown here.

{{ $siteName }}
</x-mail::message>

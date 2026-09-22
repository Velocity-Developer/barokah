<x-mail::message>
# Thank you for your order!

Hi {{ $order->customer_name }}, we have received your order **{{ $order->order_number }}**.

<x-mail::table>
| Product | Store | Qty | Subtotal |
|:--|:--|:-:|--:|
@foreach ($items as $item)
| {{ $item['name'] }} | {{ $item['store'] }} | {{ $item['quantity'] }} | {{ $item['subtotal'] }} |
@endforeach
| | | Subtotal | {{ $subtotal }} |
@if ($discount)
| | | Discount | −{{ $discount }} |
@endif
| | | Shipping | {{ $shipping }} |
| | | **Total** | **{{ $total }}** |
</x-mail::table>

**Payment method:** {{ $paymentLabel }}

@if ($payBy)
Please complete your payment before **{{ $payBy }}**. Unpaid orders expire after that time.
@endif

@if ($bankDetails && $bankDetails['bank_account_number'])
<x-mail::panel>
**Bank transfer details**<br>
Bank: {{ $bankDetails['bank_name'] }}<br>
Account name: {{ $bankDetails['bank_account_name'] }}<br>
Account number: {{ $bankDetails['bank_account_number'] }}<br>
Please upload your transfer receipt on the order page.
</x-mail::panel>
@endif

<x-mail::button :url="$orderUrl">
View order &amp; payment
</x-mail::button>

@if ($deliveryAddress)
**Delivery to:** {{ $deliveryAddress }}
@endif

Keep your order number to track the delivery on our Track Order page.

Thanks,<br>
{{ $siteName }}
</x-mail::message>

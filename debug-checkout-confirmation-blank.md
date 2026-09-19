# Debug Checkout Confirmation Blank

Status: [OPEN]
Session: checkout-confirmation-blank

## Symptom
Checkout confirmation URL renders blank:
`/checkout/confirmation/BRK-20260919-NRUNJF?payment_method=fpx`

## Hypotheses
1. CheckoutController confirmation fails while loading order or relations.
2. `payment_method=fpx` is not handled by frontend.
3. Confirmation Vue page has a runtime error.
4. Vite bundle/chunk is stale or missing.
5. Guest/authenticated order lookup rejects this order.

## Evidence
Pending.

## Fix
Pending.

## Verification
Pending user confirmation.

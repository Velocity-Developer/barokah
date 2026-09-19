# Debug Tracking Blank Page

Status: [OPEN]
Session: tracking-blank-page

## Symptoms
Tracking page renders blank.

## Hypotheses
1. TrackingController throws while loading or serializing seller tracking data.
2. Tracking Vue component has a compile/runtime error after multi-seller changes.
3. `/tracking` route does not resolve to the expected controller.
4. Browser serves stale or missing Vite assets.
5. Failure occurs only when an order query is supplied.

## Evidence
Pending runtime reproduction.

## Fix
Pending.

## Verification
Pending user confirmation.

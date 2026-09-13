# Debug settings upload

Status: [OPEN]

## Symptom
Admin settings upload shows `Settings are temporarily unavailable.`

## Hypotheses
1. CSRF failure returns 419.
2. POST route mismatch returns 405 or redirect.
3. Non-JSON response causes response parsing failure.
4. Upload/settings validation returns 422.
5. Server/database failure returns 500.

## Reproduction
Open `/admin/settings/homepage`, upload banner, click Save, inspect response.

## Evidence
Pending runtime request.

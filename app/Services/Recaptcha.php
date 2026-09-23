<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA, configured from Settings → Security.
 *
 * The check is skipped while the feature is off or the keys are missing, so
 * switching it on without keys cannot lock people out of the site.
 */
class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private SettingsService $settings) {}

    /**
     * Whether a given place ('login' or 'guest_checkout') needs a captcha.
     */
    public function required(string $context): bool
    {
        if (! $this->configured()) {
            return false;
        }

        return (bool) $this->settings->get("security.recaptcha_on_{$context}", false);
    }

    public function configured(): bool
    {
        return (bool) $this->settings->get('security.recaptcha_enabled', false)
            && $this->siteKey() !== ''
            && $this->secretKey() !== '';
    }

    public function siteKey(): string
    {
        return trim((string) $this->settings->get('security.recaptcha_site_key', ''));
    }

    public function version(): string
    {
        return $this->settings->get('security.recaptcha_version', 'v2') === 'v3' ? 'v3' : 'v2';
    }

    /**
     * Ask Google whether the token is genuine. A v3 token must also score
     * above the configured threshold.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->configured()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $exception) {
            // Google unreachable: let the visitor through rather than block checkout.
            Log::warning('reCAPTCHA verification failed to reach Google.', ['message' => $exception->getMessage()]);

            return true;
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return false;
        }

        if ($this->version() === 'v3') {
            return (float) ($payload['score'] ?? 0) >= $this->scoreThreshold();
        }

        return true;
    }

    private function scoreThreshold(): float
    {
        return max(0.0, min(1.0, (float) $this->settings->get('security.recaptcha_score_threshold', 0.5)));
    }

    private function secretKey(): string
    {
        return trim((string) $this->settings->get('security.recaptcha_secret_key', ''));
    }
}

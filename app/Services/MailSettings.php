<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

/**
 * Applies the sender and SMTP details saved in Admin → Settings → Email to
 * the mailer. Called right before sending so long-running queue workers pick
 * up changes without a restart.
 */
class MailSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    public function apply(): void
    {
        $fromAddress = $this->string('email.from_address');
        $fromName = $this->string('email.from_name');

        if ($fromAddress !== null) {
            config(['mail.from.address' => $fromAddress]);
        }

        if ($fromName !== null) {
            config(['mail.from.name' => $fromName]);
        }

        if ($this->settings->get('email.smtp_enabled') !== true || $this->string('email.smtp_host') === null) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $this->string('email.smtp_host'),
            'mail.mailers.smtp.port' => (int) ($this->settings->get('email.smtp_port') ?: 587),
            'mail.mailers.smtp.username' => $this->string('email.smtp_username'),
            'mail.mailers.smtp.password' => $this->string('email.smtp_password'),
            // "ssl" means implicit TLS (smtps, usually port 465); tls/starttls upgrade on a plain connection.
            'mail.mailers.smtp.scheme' => $this->settings->get('email.smtp_encryption') === 'ssl' ? 'smtps' : 'smtp',
        ]);

        Mail::purge('smtp');
    }

    private function string(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

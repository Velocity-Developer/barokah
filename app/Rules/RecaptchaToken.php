<?php

namespace App\Rules;

use App\Services\Recaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a reCAPTCHA token against Google. Passes straight through when
 * the captcha is not switched on for this part of the site.
 */
class RecaptchaToken implements ValidationRule
{
    public function __construct(private string $context) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $recaptcha = app(Recaptcha::class);

        if (! $recaptcha->required($this->context)) {
            return;
        }

        if (! $recaptcha->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail(__('Please complete the captcha and try again.'));
        }
    }
}

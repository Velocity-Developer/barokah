<?php

namespace App\Actions\Fortify;

use App\Services\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Checks the login captcha before the credentials are tried, so a bot cannot
 * use the login form to guess passwords.
 */
class EnsureRecaptchaPassed
{
    public function __construct(private Recaptcha $recaptcha) {}

    public function handle(Request $request, callable $next): mixed
    {
        if ($this->recaptcha->required('login') && ! $this->recaptcha->verify($request->input('recaptcha_token'), $request->ip())) {
            throw ValidationException::withMessages([
                'recaptcha_token' => __('Please complete the captcha and try again.'),
            ]);
        }

        return $next($request);
    }
}

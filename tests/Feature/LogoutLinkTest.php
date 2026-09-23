<?php

use Illuminate\Support\Facades\File;

/**
 * The storefront menu once pointed its "Log out" button at the login route,
 * which quietly did nothing (and bounced some roles to their dashboard).
 */
it('points every log out button at the logout route', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('js')) as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        $contents = $file->getContents();

        if (! str_contains($contents, 'Log out')) {
            continue;
        }

        if (! str_contains($contents, 'logout()')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

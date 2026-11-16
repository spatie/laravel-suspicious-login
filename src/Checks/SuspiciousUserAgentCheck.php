<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

/**
 * Flags logins without a user agent or from HTTP clients and headless browsers that people don't log in with.
 */
class SuspiciousUserAgentCheck extends Check
{
    protected int $score = 20;

    /** @var array<int, string> */
    protected array $patterns = [
        'curl',
        'wget',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'java/',
        'okhttp',
        'libwww-perl',
        'scrapy',
        'headlesschrome',
        'phantomjs',
        'selenium',
        'puppeteer',
        'playwright',
    ];

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $userAgent = strtolower(trim((string) $attempt->user_agent));

        if ($userAgent === '') {
            return $this->flag(__('Login without a user agent'));
        }

        foreach ($this->patterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return $this->flag(__('Login from an automated client (:client)', ['client' => $pattern]));
            }
        }

        return $this->pass();
    }
}

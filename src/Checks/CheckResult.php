<?php

namespace Spatie\SuspiciousLogin\Checks;

readonly class CheckResult
{
    public function __construct(
        public string $check,
        public bool $suspicious,
        public int $score = 0,
        public ?string $reason = null,
    ) {}

    public static function passed(string $check): self
    {
        return new self($check, suspicious: false);
    }

    public static function suspicious(string $check, int $score, string $reason): self
    {
        return new self($check, suspicious: true, score: $score, reason: $reason);
    }
}

<?php

namespace App\Services\Analysis;

use RuntimeException;

/**
 * The analyzer cannot judge any article right now (quota, outage, missing key).
 * The article itself is not at fault: it stays pending and is retried later.
 */
class AnalyzerUnavailable extends RuntimeException
{
    private function __construct(string $message, public readonly int $retryAfter, public readonly bool $deferToNextRun)
    {
        parent::__construct($message);
    }

    /** Short outage or quota: retry within the same collection after a pause. */
    public static function temporary(string $message, int $retryAfter = 60): self
    {
        return new self($message, max(10, min(600, $retryAfter)), false);
    }

    /** Configuration problem: retrying in a few minutes is useless, wait for the next collection. */
    public static function misconfigured(string $message): self
    {
        return new self($message, 900, true);
    }
}

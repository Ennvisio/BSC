<?php

namespace App\Sync\Engine;

use RuntimeException;
use Throwable;

/**
 * The cloud could not be reached, or answered with an error. Retried with
 * backoff; nothing is lost, because every pending change stays in the
 * database until the cloud acknowledges it.
 */
class SyncTransportException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** No answer at all: offline, DNS, timeout, connection reset. */
    public function isNetwork(): bool
    {
        return $this->status === null;
    }
}

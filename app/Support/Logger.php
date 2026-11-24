<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/*
 * Records errors with enough detail to debug and none of it ever shown to users:
 * in PHP's error log, which reaches the container logs (docker compose logs, the
 * Railway log viewer), and in storage/logs/app.log for local inspection.
 */
final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Writes an unexpected exception with its stack trace.
     * @return void
     */
    public function error(string $message, ?Throwable $exception = null): void
    {
        $line = sprintf("[%s] ERROR %s%s\n", date('c'), $message, $exception === null ? '' : "\n" . $exception);

        // The container's filesystem is lost on restart, so the error log always gets it.
        error_log(rtrim($line));
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}

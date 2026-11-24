<?php

declare(strict_types=1);

/*
 * The only public PHP entry point: Apache sends every request that isn't a file here.
 */

use App\Http\Request;
use App\Http\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $kernel = (require dirname(__DIR__) . '/bootstrap/app.php')(require dirname(__DIR__) . '/config/app.php');
} catch (Throwable $e) {
    // Configuration failed (e.g. a missing environment variable): log it, show nothing internal.
    error_log('Bootstrap failed: ' . $e);
    (new Response(500, "Internal Server Error\n", ['Content-Type' => 'text/plain; charset=utf-8']))->send();

    return;
}

$kernel->handle(Request::fromGlobals())->send();

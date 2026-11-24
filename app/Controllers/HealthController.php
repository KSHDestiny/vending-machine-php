<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Support\Logger;
use PDOException;

final class HealthController
{
    public function __construct(
        private readonly Database $database,
        private readonly Logger $logger,
    ) {
    }

    /**
     * 200 when MySQL answers, 503 when it doesn't; used by the hosting health check.
     * @return Response
     */
    #[Route('/health')]
    public function check(Request $request): Response
    {
        try {
            $this->database->pdo()->query('SELECT 1');
        } catch (PDOException $e) {
            $this->logger->error('Health check: database unavailable', $e);

            return Response::json(['status' => 'error', 'database' => 'unavailable'], 503);
        }

        return Response::json(['status' => 'ok', 'database' => 'ok']);
    }
}

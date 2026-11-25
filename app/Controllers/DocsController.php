<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\OpenApi;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\View;

/*
 * Public API documentation: the OpenAPI document and a Swagger UI page to try the API.
 */
final class DocsController
{
    public function __construct(private readonly View $view)
    {
    }

    /**
     * Swagger UI for the API.
     * @return Response
     */
    #[Route('/docs')]
    public function index(Request $request): Response
    {
        return $this->view->standalone('docs');
    }

    /**
     * The OpenAPI 3 document as JSON.
     * @return Response
     */
    #[Route('/openapi.json')]
    public function spec(Request $request): Response
    {
        return Response::json(OpenApi::document());
    }
}

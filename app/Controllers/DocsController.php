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
     * Swagger UI for the API. Its policy also allows the pinned CDN files and the page's one
     * inline start-up script, by the hash of its exact contents.
     * @return Response
     */
    #[Route('/docs')]
    public function index(Request $request): Response
    {
        $page = $this->view->standalone('docs');
        preg_match('#<script>(.*?)</script>#s', $page->body, $inline);
        $hash = base64_encode(hash('sha256', $inline[1] ?? '', true));
        $cdn = 'https://cdn.jsdelivr.net';

        return new Response($page->status, $page->body, $page->headers + [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' {$cdn} 'sha256-{$hash}'; style-src 'self' {$cdn} 'unsafe-inline'; "
                . "img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
        ]);
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

<?php
/**
 * Swagger UI for /openapi.json. The two files come from a CDN pinned to one version
 * with Subresource Integrity, so the browser refuses them if they ever change.
 *
 * @var callable(string|int|float|null): string $e
 */
$cdn = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.35.0';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API docs · Vending Machine</title>
    <link rel="stylesheet" href="<?= $e($cdn) ?>/swagger-ui.css" integrity="sha384-WlYgdmXYOOBWL5apwjDEZwN+U3HmXSSu5R6V5aGxEcf7z+KuRNwBhoxKVN1i3D5I" crossorigin="anonymous">
    <style>
        body { margin: 0; background: #f8fafc; }
        .docs-bar { display: flex; align-items: center; justify-content: space-between; padding: 12px 24px; background: #4f46e5; color: #fff; font: 600 15px/1.4 system-ui, sans-serif; }
        .docs-bar a { color: #e0e7ff; font-weight: 500; text-decoration: none; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
<header class="docs-bar"><span>Vending Machine API</span><a href="/">← Back to the app</a></header>
<div id="swagger-ui"></div>
<script src="<?= $e($cdn) ?>/swagger-ui-bundle.js" integrity="sha384-gjRm0bEPMeTmMh5jT2cHccIwCUZSTd8YC+j0d0sXw0MdGEfScduGpSyF3ZSSLkX9" crossorigin="anonymous"></script>
<script>
    window.addEventListener('load', () => {
        SwaggerUIBundle({ url: "/openapi.json", dom_id: '#swagger-ui', deepLinking: true, persistAuthorization: false, tryItOutEnabled: true });
    });
</script>
</body>
</html>

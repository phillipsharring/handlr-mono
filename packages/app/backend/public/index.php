<?php

/**
 * Web Application Entrypoint
 *
 * The public-facing entrypoint for the Handlr application. All HTTP requests
 * should be routed to this file via your web server configuration.
 *
 * This file:
 * - Loads the bootstrap to initialize the app container and config
 * - Creates the Router and Kernel instances
 * - Builds a Request from PHP superglobals
 * - Dispatches the request through the router pipeline
 * - Sends the response back to the client
 * - Handles uncaught exceptions with appropriate error responses
 *
 * @example Apache .htaccess configuration
 * ```apache
 * RewriteEngine On
 * RewriteCond %{REQUEST_FILENAME} !-f
 * RewriteCond %{REQUEST_FILENAME} !-d
 * RewriteRule ^ index.php [L]
 * ```
 *
 * @example Nginx configuration
 * ```nginx
 * location / {
 *     try_files $uri $uri/ /index.php?$query_string;
 * }
 *
 * location ~ \.php$ {
 *     fastcgi_pass unix:/var/run/php/php-fpm.sock;
 *     fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
 *     include fastcgi_params;
 * }
 * ```
 *
 * @example PHP built-in server (development)
 * ```bash
 * php -S localhost:8000 -t public
 * ```
 */

require_once __DIR__ . '/../bootstrap.php';

use Handlr\Core\Kernel;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Core\Routes\Router;

try {
    $app = handlr_app();
    $container = $app['container'];

    $router = new Router($container);
    $kernel = Kernel::getInstance($container, $router, HANDLR_APP_ROOT);

    $request = Request::fromGlobals();
    $response = new Response;

    $response = $kernel->getRouter()->dispatch($request, $response);
    $response->send();
} catch (Throwable $e) {
    $response = new Response;
    $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

    $errorData = [
        'status' => 'error',
        'message' => $e->getMessage(),
    ];

    if (!$isProduction) {
        $errorData['debug'] = [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }

    $response->withJson($errorData, Response::HTTP_SERVER_ERROR)->send();
}

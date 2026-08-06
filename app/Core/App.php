<?php

namespace App\Core;

use App\Config\Paths;

class App
{
  public function run()
{
    $routes = require Paths::appRoot() . '/routes/web.php';

    $method = $_SERVER['REQUEST_METHOD'];

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = is_string($uri) ? $uri : '/';

    $base = Paths::urlBasePath();

    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    $uri = str_replace('/index.php', '', $uri);

    $uri = '/' . trim($uri, '/');

    if ($uri === '//') {
        $uri = '/';
    }

    $availableRoutes = $routes[$method] ?? [];

    /*
    =========================================
           BUSCAR RUTA EXACTA
    =========================================
    */

    if (isset($availableRoutes[$uri])) {

        [$controller, $action] =
            $availableRoutes[$uri];

        $controller = new $controller();

        $controller->$action();

        return;
    }

    /*
    =========================================
         BUSCAR RUTAS CON PARÁMETROS
    =========================================
    */

    foreach ($availableRoutes as $route => $handler) {

        if (!str_contains($route, '{')) {
            continue;
        }

        $pattern = preg_replace(
            '/\{[a-zA-Z_][a-zA-Z0-9_]*\}/',
            '([^/]+)',
            $route
        );

        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) {
            continue;
        }

        array_shift($matches);

        [$controller, $action] = $handler;

        $controller = new $controller();

        $controller->$action(...$matches);

        return;
    }

    http_response_code(404);

    echo "<h1>404 - Página no encontrada</h1>";

    exit;
}
}
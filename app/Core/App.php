<?php

namespace App\Core;

class App
{
    public function run()
    {
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';

        $method = $_SERVER['REQUEST_METHOD'];

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $base = '/consultorio_psicologico/public';

        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        // Elimina index.php si viene en la URL
        $uri = str_replace('/index.php', '', $uri);

        $uri = '/' . trim($uri, '/');

        if ($uri === '//') {
            $uri = '/';
        }

        if (!isset($routes[$method][$uri])) {

            http_response_code(404);

            echo "<h1>404 - Página no encontrada</h1>";

            exit;
        }

        [$controller, $action] = $routes[$method][$uri];

        $controller = new $controller();

        $controller->$action();
    }
}
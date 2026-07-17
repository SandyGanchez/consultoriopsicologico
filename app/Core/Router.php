<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $uri, array $action): void
    {
        $this->routes['GET'][$this->normalize($uri)] = $action;
    }

    public function post(string $uri, array $action): void
    {
        $this->routes['POST'][$this->normalize($uri)] = $action;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $base = '/consultorio_psicologico/public';

        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = $this->normalize($uri);

        if (!isset($this->routes[$method][$uri])) {

            http_response_code(404);

            echo "<h1>404 - Página no encontrada</h1>";

            exit;

        }

        [$controller, $function] = $this->routes[$method][$uri];

        $controller = new $controller();

        $controller->$function();
    }

    private function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');

        return $uri === '/' ? '/' : rtrim($uri, '/');
    }
}
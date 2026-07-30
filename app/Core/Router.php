<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(
        string $uri,
        array $action
    ): void {
        $this->routes['GET'][
            $this->normalize($uri)
        ] = $action;
    }

    public function post(
        string $uri,
        array $action
    ): void {
        $this->routes['POST'][
            $this->normalize($uri)
        ] = $action;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );

        $base = '/consultorio_psicologico/public';

        if (
            is_string($uri)
            && str_starts_with($uri, $base)
        ) {
            $uri = substr(
                $uri,
                strlen($base)
            );
        }

        $uri = $this->normalize(
            is_string($uri) ? $uri : '/'
        );

        $routes = $this->routes[$method] ?? [];

        /*
        =====================================
              BUSCAR RUTA EXACTA
        =====================================
        */

        if (isset($routes[$uri])) {
            $this->execute(
                $routes[$uri],
                []
            );

            return;
        }

        /*
        =====================================
             BUSCAR RUTA DINÁMICA
        =====================================
        */

        foreach ($routes as $route => $action) {
            if (!str_contains($route, '{')) {
                continue;
            }

            $parameterNames = [];

            preg_match_all(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                $route,
                $parameterMatches
            );

            $parameterNames =
                $parameterMatches[1] ?? [];

            $pattern = preg_quote(
                $route,
                '#'
            );

            $pattern = preg_replace(
                '/\\\\\{[a-zA-Z_][a-zA-Z0-9_]*\\\\\}/',
                '([^/]+)',
                $pattern
            );

            $pattern = '#^' . $pattern . '$#';

            if (
                !preg_match(
                    $pattern,
                    $uri,
                    $matches
                )
            ) {
                continue;
            }

            array_shift($matches);

            $parameters = array_map(
                static fn (
                    string $value
                ): string => rawurldecode($value),
                $matches
            );

            $this->execute(
                $action,
                $parameters
            );

            return;
        }

        http_response_code(404);

        echo '<h1>404 - Página no encontrada</h1>';
    }

    private function execute(
        array $action,
        array $parameters
    ): void {
        [
            $controllerClass,
            $function
        ] = $action;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException(
                'El controlador no existe: '
                . $controllerClass
            );
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $function)) {
            throw new \RuntimeException(
                'El método no existe: '
                . $controllerClass
                . '::'
                . $function
            );
        }

        $controller->$function(
            ...$parameters
        );
    }

    private function normalize(
        string $uri
    ): string {
        $uri = '/' . trim($uri, '/');

        return $uri === '/'
            ? '/'
            : rtrim($uri, '/');
    }
}
<?php

namespace App\Core;

class Controller
{
    protected function view(
        string $view,
        array $data = [],
        string $layout = 'master'
    ): void {
        extract($data);

        $content = dirname(__DIR__) . "/Views/$view.php";

        $layoutFile =
            dirname(__DIR__) . "/Views/layouts/$layout.php";

        if (!file_exists($content)) {
            throw new \RuntimeException(
                "No se encontró la vista: $content"
            );
        }

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException(
                "No se encontró el layout: $layoutFile"
            );
        }

        require $layoutFile;
    }
}
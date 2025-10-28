<?php

namespace App\Support;

class View
{
    public static function make(string $template, array $data = []): string
    {
        $path = __DIR__ . '/../../resources/views/' . $template . '.php';
        if (!file_exists($path)) {
            throw new \RuntimeException("View {$template} not found");
        }

        extract($data, EXTR_SKIP);
        $viewTemplate = $template;
        ob_start();
        include __DIR__ . '/../../resources/views/layouts/app.php';
        return ob_get_clean();
    }

    public static function renderPartial(string $template, array $data = []): string
    {
        $path = __DIR__ . '/../../resources/views/' . $template . '.php';
        if (!file_exists($path)) {
            throw new \RuntimeException("View {$template} not found");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }
}

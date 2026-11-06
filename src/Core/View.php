<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $viewsPath = __DIR__ . '/../../views';

    public static function render(string $template, array $data = [], ?string $layout = 'main'): void
    {
        $content = self::renderPartial($template, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = self::$viewsPath . "/layouts/{$layout}.php";
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout file not found: {$layoutFile}");
        }

        extract($data, EXTR_SKIP);
        require $layoutFile;
    }

    public static function renderPartial(string $template, array $data = []): string
    {
        $templateFile = self::$viewsPath . "/{$template}.php";
        if (!file_exists($templateFile)) {
            throw new \RuntimeException("View template not found: {$templateFile}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;
        return ob_get_clean() ?: '';
    }
}

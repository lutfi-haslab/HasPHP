<?php
namespace Hasphp\App\Core;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

class View {
    private static Environment $twig;

    public static function init(string $path) {
        $loader = new FilesystemLoader($path);
        self::$twig = new Environment($loader);
    }

    public static function render(string $template, array $data = []): string {
        return self::$twig->render($template, $data);
    }
}
<?php

declare(strict_types=1);

final class Autoload
{
    private const CLASS_MAP = [
        'App' => __DIR__ . '/src/',
    ];

    static function register(): void
    {
        spl_autoload_register(function (string $className) {
            $parts = explode('\\', $className);

            $namespace = array_shift($parts);
            $classFile = array_pop($parts) . '.php';

            if (!array_key_exists($namespace, self::CLASS_MAP)) {
                return;
            }

            $path = implode(DIRECTORY_SEPARATOR, $parts);
            $file = self::CLASS_MAP[$namespace] . $path . DIRECTORY_SEPARATOR . $classFile;

            if (!file_exists($file) && !class_exists($className)) {
                return;
            }

            require_once $file;
        });
    }

    private function __construct()
    {
        // This is a static class
    }
}

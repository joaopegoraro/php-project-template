<?php

declare(strict_types=1);

namespace App;

class App
{
    public static string $ROOT_DIR;

    public function __construct(string $rootDir)
    {
        self::$ROOT_DIR = $rootDir;
    }
}
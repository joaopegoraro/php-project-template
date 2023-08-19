<?php

declare(strict_types=1);

namespace App\Core;

class Renderer
{
    private readonly string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function render(
        string $view,
        array $data,
    ): string {
        foreach ($data as $key => $value) {
            $$key = $value;
        }

        ob_start();
        include $this->path . $view . '.php';

        return ob_get_clean();
    }
}

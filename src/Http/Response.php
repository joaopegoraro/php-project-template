<?php

declare(strict_types=1);

namespace App\Http;

class Response
{

    public readonly int $status;
    public readonly array $headers;
    public readonly string $content;

    /**
     * @param int $status
     * @param array $headers
     * @param string|null $content
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        ?string $content = null,
    ) {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers ?? [];
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value);
        }

        echo $this->content;

        exit(0);
    }
}
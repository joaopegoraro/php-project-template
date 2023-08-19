<?php

declare(strict_types=1);

namespace App\Http;

class Request
{
    public array $context;
    public readonly string $url;
    public readonly array $headers;
    public readonly string $method;
    public readonly array $body;
    public readonly array $routeParams;
    public readonly array $queryParams;
    public readonly string $contentType;

    public function __construct(
        string $method,
        string $url,
        ?array $headers,
        ?array $context = [],
        ?array $body = [],
        ?array $routeParams = [],
        ?array $queryParams = [],
        ?string $contentType = ""
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers ?? [];
        $this->context = $context ?? [];
        $this->body = $body ?? [];
        $this->routeParams = $routeParams ?? [];
        $this->queryParams = $queryParams ?? [];
        $this->contentType = $contentType ?? "";
    }
}

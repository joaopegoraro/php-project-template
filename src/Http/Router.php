<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Middleware;

class Router
{
    /** @var Middleware[] $middlewares */
    private readonly array $middlewares;

    /**
     * @param Middleware[] $defaultMiddlewares
     */
    public function __construct(array $defaultMiddlewares = [])
    {
        $this->middlewares = $defaultMiddlewares;
    }

    /**
     * @param Middleware[] $middlewares
     * @param callable(Router $router): void $useRouterWithMiddlewares
     * @return void
     */
    public function withMiddlewares(array $middlewares, callable $useRouterWithMiddlewares): void
    {
        $routerWithMiddlewares = new Router($this->middlewares + $middlewares);
        $useRouterWithMiddlewares($routerWithMiddlewares);
    }

    /**
     * @param string $route
     * @param callable(Request $request): Response $onMatch
     * @return void
     */
    public function get(string $route, callable $onMatch): void
    {
        if (strcasecmp($_SERVER['REQUEST_METHOD'], 'GET') !== 0) {
            return;
        }

        $this->on($route, $onMatch);
    }

    /**
     * @param string $route
     * @param callable(Request $request): Response $onMatch
     * @return void
     */
    public function post(string $route, callable $onMatch): void
    {
        if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') !== 0) {
            return;
        }

        $this->on($route, $onMatch);
    }

    /**
     * @param string $route
     * @param callable(Request $request): Response $onMatch
     * @return void
     */
    public function put(string $route, callable $onMatch): void
    {
        if (strcasecmp($_SERVER['REQUEST_METHOD'], 'PUT') !== 0) {
            return;
        }

        $this->on($route, $onMatch);
    }

    /**
     * @param string $route
     * @param callable(Request $request): Response $onMatch
     * @return void
     */
    public function delete(string $route, callable $onMatch): void
    {
        if (strcasecmp($_SERVER['REQUEST_METHOD'], 'DELETE') !== 0) {
            return;
        }

        $this->on($route, $onMatch);
    }

    /**
     * @param string $route
     * @param callable(Request $request): Response $onMatch
     * @return void
     */
    private function on(string $route, callable $onMatch): void
    {
        $url = $_SERVER['REQUEST_URI'];

        // replaces route params with regex. ex: {id} is replaced with [\w-]+
        $regex = preg_replace(
            pattern: "/\{([\w-]+)\}/",
            replacement: "(?<$1>[\w\-]+)",
            subject: '#^' . str_replace('/', '\/', $route) . '(\?[\w\-=]*|\/?)$#'
        );

        $matches = [];
        if (preg_match_all($regex, $url, $matches)) {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            foreach ($body as $key => $value) {
                $body[$key] = filter_var($value, FILTER_UNSAFE_RAW);
            }

            $queryParams = [];
            foreach ($_GET as $key => $value) {
                $queryParams[$key] = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
            }

            $request = new Request(
                method: trim($_SERVER['REQUEST_METHOD']),
                url: $url,
                headers: getallheaders(),
                body: $body,
                routeParams: array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
                queryParams: $queryParams,
                contentType: !empty($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : ''
            );

            foreach ($this->middlewares as $middleware) {
                $response = $middleware->execute($request);
                // if the middleware returns a response, send the response (which also stops the script)
                $response?->send();
            }

            /* @var Response $response */
            $response = $onMatch($request);
            $response->send();
        }
    }
}
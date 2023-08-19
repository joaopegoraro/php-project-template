<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Request;
use App\Http\Response;

interface Middleware
{

    /**
     * Executes the Middleware.
     *
     * It receives the reference of the request as a parameter, which lets the Middleware modify
     * the {@see Request::$context}.
     *
     * @param Request $request
     * @return Response|null If it returns a response, it means the response should be sent
     *  and the request flow should be aborted.
     */
    public function execute(Request &$request): Response|null;
}
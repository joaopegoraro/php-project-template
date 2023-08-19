<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Http\Request;
use App\Http\Response;

class ExampleController extends Controller
{

    public function doStuff(Request $request): Response
    {
        return $this->view("example", ["url" => $request->url]);
    }
}

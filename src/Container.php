<?php

declare(strict_types=1);

namespace App;

use App\Controller\ExampleController;
use App\Core\Renderer;

class Container
{
    /**
     * @var Renderer $baseRenderer
     */

    private readonly Renderer $baseRenderer;

    public function getBaseRenderer(): Renderer
    {
        return $this->baseRenderer ?? $this->baseRenderer = new Renderer(path: App::$ROOT_DIR);
    }

    /**
     * @var ExampleController $exampleControlelr
     */
    private readonly ExampleController $exampleController;

    public function getExampleController(): ExampleController
    {
        return $this->exampleController ?? $this->exampleController = new ExampleController(
            renderer: $this->getBaseRenderer(),
        );
    }
}

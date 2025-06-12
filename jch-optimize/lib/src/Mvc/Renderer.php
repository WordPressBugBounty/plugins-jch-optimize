<?php

namespace JchOptimize\Core\Mvc;

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\Response;
use _JchOptimizeVendor\V91\Joomla\Renderer\AbstractRenderer;
use _JchOptimizeVendor\V91\Slim\Views\PhpRenderer;
use Throwable;

use function array_merge;

class Renderer extends AbstractRenderer
{
    private PhpRenderer $renderer;

    public function __construct(PhpRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function pathExists(string $path): bool
    {
        return $this->getRenderer()->templateExists($path);
    }

    public function getRenderer(): PhpRenderer
    {
        return $this->renderer;
    }

    /**
     * @throws Throwable
     */
    public function render(string $template, array $data = []): string
    {
        $data = array_merge($this->data, $data);

        $response = $this->getRenderer()->render(new Response(), $template, $data);

        return $response->getBody();
    }
}

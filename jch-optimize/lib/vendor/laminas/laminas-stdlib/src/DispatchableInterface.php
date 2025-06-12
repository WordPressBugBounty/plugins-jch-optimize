<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\Stdlib;

interface DispatchableInterface
{
    /**
     * Dispatch a request
     *
     * @return Response|mixed
     */
    public function dispatch(RequestInterface $request, ?ResponseInterface $response = null);
}

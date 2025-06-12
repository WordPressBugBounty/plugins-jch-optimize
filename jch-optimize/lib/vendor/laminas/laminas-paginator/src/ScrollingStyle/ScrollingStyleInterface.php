<?php

namespace _JchOptimizeVendor\V91\Laminas\Paginator\ScrollingStyle;

use _JchOptimizeVendor\V91\Laminas\Paginator\Paginator;

interface ScrollingStyleInterface
{
    /**
     * Returns an array of "local" pages given a page number and range.
     *
     * @param  int $pageRange (Optional) Page range
     * @return array
     */
    public function getPages(Paginator $paginator, $pageRange = null);
}

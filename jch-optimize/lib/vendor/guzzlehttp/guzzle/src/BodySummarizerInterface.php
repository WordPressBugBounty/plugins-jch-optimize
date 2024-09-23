<?php

namespace _JchOptimizeVendor\GuzzleHttp;

use _JchOptimizeVendor\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string;
}

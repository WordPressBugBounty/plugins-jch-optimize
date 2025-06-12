<?php

namespace _JchOptimizeVendor\V91\GuzzleHttp;

use _JchOptimizeVendor\V91\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}

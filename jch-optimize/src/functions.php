<?php

namespace JchOptimize\WordPress;

use function base64_decode;
use function strtr;

if (! function_exists('JchOptimize\WordPress\base64_encode_url')) {
    function base64_encode_url(string $string): string
    {
        return strtr(base64_encode($string), '+/=', '._-');
    }
}

if (! function_exists('JchOptimize\WordPress\base64_decode_url')) {
    function base64_decode_url(string $string): string
    {
        return base64_decode(strtr($string, '._-', '+/='));
    }
}

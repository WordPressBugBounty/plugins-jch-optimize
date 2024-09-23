<?php

namespace _JchOptimizeVendor;

/**
* PCRE Regular expressions for UTF-8. Note this file is not actually used by
* the rest of the library but these regular expressions can be useful to have
* available.
* @see http://www.w3.org/International/questions/qa-forms-utf-8
* @package utf8
*/
//--------------------------------------------------------------------
/**
* PCRE Pattern to check a UTF-8 string is valid
* Comes from W3 FAQ: Multilingual Forms
* Note: modified to include full ASCII range including control chars
* @see http://www.w3.org/International/questions/qa-forms-utf-8
* @package utf8
*/
$UTF8_VALID = '^(' . '[\\x00-\\x7F]' . '|[\\xC2-\\xDF][\\x80-\\xBF]' . '|\\xE0[\\xA0-\\xBF][\\x80-\\xBF]' . '|[\\xE1-\\xEC\\xEE\\xEF][\\x80-\\xBF]{2}' . '|\\xED[\\x80-\\x9F][\\x80-\\xBF]' . '|\\xF0[\\x90-\\xBF][\\x80-\\xBF]{2}' . '|[\\xF1-\\xF3][\\x80-\\xBF]{3}' . '|\\xF4[\\x80-\\x8F][\\x80-\\xBF]{2}' . ')*$';
//--------------------------------------------------------------------
/**
* PCRE Pattern to match single UTF-8 characters
* Comes from W3 FAQ: Multilingual Forms
* Note: modified to include full ASCII range including control chars
* @see http://www.w3.org/International/questions/qa-forms-utf-8
* @package utf8
*/
$UTF8_MATCH = '([\\x00-\\x7F])' . '|([\\xC2-\\xDF][\\x80-\\xBF])' . '|(\\xE0[\\xA0-\\xBF][\\x80-\\xBF])' . '|([\\xE1-\\xEC\\xEE\\xEF][\\x80-\\xBF]{2})' . '|(\\xED[\\x80-\\x9F][\\x80-\\xBF])' . '|(\\xF0[\\x90-\\xBF][\\x80-\\xBF]{2})' . '|([\\xF1-\\xF3][\\x80-\\xBF]{3})' . '|(\\xF4[\\x80-\\x8F][\\x80-\\xBF]{2})';
# plane 16
//--------------------------------------------------------------------
/**
* PCRE Pattern to locate bad bytes in a UTF-8 string
* Comes from W3 FAQ: Multilingual Forms
* Note: modified to include full ASCII range including control chars
* @see http://www.w3.org/International/questions/qa-forms-utf-8
* @package utf8
*/
$UTF8_BAD = '([\\x00-\\x7F]' . '|[\\xC2-\\xDF][\\x80-\\xBF]' . '|\\xE0[\\xA0-\\xBF][\\x80-\\xBF]' . '|[\\xE1-\\xEC\\xEE\\xEF][\\x80-\\xBF]{2}' . '|\\xED[\\x80-\\x9F][\\x80-\\xBF]' . '|\\xF0[\\x90-\\xBF][\\x80-\\xBF]{2}' . '|[\\xF1-\\xF3][\\x80-\\xBF]{3}' . '|\\xF4[\\x80-\\x8F][\\x80-\\xBF]{2}' . '|(.{1}))';
# invalid byte

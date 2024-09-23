<?php

namespace _JchOptimizeVendor;

/**
* Tools for conversion between UTF-8 and unicode
* The Original Code is Mozilla Communicator client code.
* The Initial Developer of the Original Code is
* Netscape Communications Corporation.
* Portions created by the Initial Developer are Copyright (C) 1998
* the Initial Developer. All Rights Reserved.
* Ported to PHP by Henri Sivonen (http://hsivonen.iki.fi)
* Slight modifications to fit with phputf8 library by Harry Fuecks (hfuecks gmail com)
* @see http://lxr.mozilla.org/seamonkey/source/intl/uconv/src/nsUTF8ToUnicode.cpp
* @see http://lxr.mozilla.org/seamonkey/source/intl/uconv/src/nsUnicodeToUTF8.cpp
* @see http://hsivonen.iki.fi/php-utf8/
* @package utf8
*/
//--------------------------------------------------------------------
/**
* Takes an UTF-8 string and returns an array of ints representing the
* Unicode characters. Astral planes are supported ie. the ints in the
* output can be > 0xFFFF. Occurrances of the BOM are ignored. Surrogates
* are not allowed.
* Returns false if the input string isn't a valid UTF-8 octet sequence
* and raises a PHP error at level E_USER_WARNING
* Note: this function has been modified slightly in this library to
* trigger errors on encountering bad bytes
*
* Joomla modification - As of PHP 7.4, curly brace access has been deprecated. As a result this function has been
* modified to use square brace syntax
* See https://github.com/php/php-src/commit/d574df63dc375f5fc9202ce5afde23f866b6450a
* for additional references
*
* @author <hsivonen@iki.fi>
* @param string UTF-8 encoded string
* @return mixed array of unicode code points or FALSE if UTF-8 invalid
* @see utf8_from_unicode
* @see http://hsivonen.iki.fi/php-utf8/
* @package utf8
*/
function utf8_to_unicode($str)
{
    $mState = 0;
    // cached expected number of octets after the current octet
    // until the beginning of the next UTF8 character sequence
    $mUcs4 = 0;
    // cached Unicode character
    $mBytes = 1;
    // cached expected number of octets in the current sequence
    $out = array();
    $len = \strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $in = \ord($str[$i]);
        if ($mState == 0) {
            // When mState is zero we expect either a US-ASCII character or a
            // multi-octet sequence.
            if (0 == (0x80 & $in)) {
                // US-ASCII, pass straight through.
                $out[] = $in;
                $mBytes = 1;
            } else {
                if (0xc0 == (0xe0 & $in)) {
                    // First octet of 2 octet sequence
                    $mUcs4 = $in;
                    $mUcs4 = ($mUcs4 & 0x1f) << 6;
                    $mState = 1;
                    $mBytes = 2;
                } else {
                    if (0xe0 == (0xf0 & $in)) {
                        // First octet of 3 octet sequence
                        $mUcs4 = $in;
                        $mUcs4 = ($mUcs4 & 0xf) << 12;
                        $mState = 2;
                        $mBytes = 3;
                    } else {
                        if (0xf0 == (0xf8 & $in)) {
                            // First octet of 4 octet sequence
                            $mUcs4 = $in;
                            $mUcs4 = ($mUcs4 & 0x7) << 18;
                            $mState = 3;
                            $mBytes = 4;
                        } else {
                            if (0xf8 == (0xfc & $in)) {
                                /* First octet of 5 octet sequence.
                                 *
                                 * This is illegal because the encoded codepoint must be either
                                 * (a) not the shortest form or
                                 * (b) outside the Unicode range of 0-0x10FFFF.
                                 * Rather than trying to resynchronize, we will carry on until the end
                                 * of the sequence and let the later error handling code catch it.
                                 */
                                $mUcs4 = $in;
                                $mUcs4 = ($mUcs4 & 0x3) << 24;
                                $mState = 4;
                                $mBytes = 5;
                            } else {
                                if (0xfc == (0xfe & $in)) {
                                    // First octet of 6 octet sequence, see comments for 5 octet sequence.
                                    $mUcs4 = $in;
                                    $mUcs4 = ($mUcs4 & 1) << 30;
                                    $mState = 5;
                                    $mBytes = 6;
                                } else {
                                    /* Current octet is neither in the US-ASCII range nor a legal first
                                     * octet of a multi-octet sequence.
                                     */
                                    \trigger_error('utf8_to_unicode: Illegal sequence identifier ' . 'in UTF-8 at byte ' . $i, \E_USER_WARNING);
                                    return \FALSE;
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // When mState is non-zero, we expect a continuation of the multi-octet
            // sequence
            if (0x80 == (0xc0 & $in)) {
                // Legal continuation.
                $shift = ($mState - 1) * 6;
                $tmp = $in;
                $tmp = ($tmp & 0x3f) << $shift;
                $mUcs4 |= $tmp;
                /**
                 * End of the multi-octet sequence. mUcs4 now contains the final
                 * Unicode codepoint to be output
                 */
                if (0 == --$mState) {
                    /*
                     * Check for illegal sequences and codepoints.
                     */
                    // From Unicode 3.1, non-shortest form is illegal
                    if (2 == $mBytes && $mUcs4 < 0x80 || 3 == $mBytes && $mUcs4 < 0x800 || 4 == $mBytes && $mUcs4 < 0x10000 || 4 < $mBytes || ($mUcs4 & 0xfffff800) == 0xd800 || $mUcs4 > 0x10ffff) {
                        \trigger_error('utf8_to_unicode: Illegal sequence or codepoint ' . 'in UTF-8 at byte ' . $i, \E_USER_WARNING);
                        return \FALSE;
                    }
                    if (0xfeff != $mUcs4) {
                        // BOM is legal but we don't want to output it
                        $out[] = $mUcs4;
                    }
                    //initialize UTF8 cache
                    $mState = 0;
                    $mUcs4 = 0;
                    $mBytes = 1;
                }
            } else {
                /**
                 *((0xC0 & (*in) != 0x80) && (mState != 0))
                 * Incomplete multi-octet sequence.
                 */
                \trigger_error('utf8_to_unicode: Incomplete multi-octet ' . '   sequence in UTF-8 at byte ' . $i, \E_USER_WARNING);
                return \FALSE;
            }
        }
    }
    return $out;
}
//--------------------------------------------------------------------
/**
* Takes an array of ints representing the Unicode characters and returns
* a UTF-8 string. Astral planes are supported ie. the ints in the
* input can be > 0xFFFF. Occurrances of the BOM are ignored. Surrogates
* are not allowed.
* Returns false if the input array contains ints that represent
* surrogates or are outside the Unicode range
* and raises a PHP error at level E_USER_WARNING
* Note: this function has been modified slightly in this library to use
* output buffering to concatenate the UTF-8 string (faster) as well as
* reference the array by it's keys
* @param array of unicode code points representing a string
* @return mixed UTF-8 string or FALSE if array contains invalid code points
* @author <hsivonen@iki.fi>
* @see utf8_to_unicode
* @see http://hsivonen.iki.fi/php-utf8/
* @package utf8
*/
function utf8_from_unicode($arr)
{
    \ob_start();
    foreach (\array_keys($arr) as $k) {
        # ASCII range (including control chars)
        if ($arr[$k] >= 0 && $arr[$k] <= 0x7f) {
            echo \chr($arr[$k]);
            # 2 byte sequence
        } else {
            if ($arr[$k] <= 0x7ff) {
                echo \chr(0xc0 | $arr[$k] >> 6);
                echo \chr(0x80 | $arr[$k] & 0x3f);
                # Byte order mark (skip)
            } else {
                if ($arr[$k] == 0xfeff) {
                    // nop -- zap the BOM
                    # Test for illegal surrogates
                } else {
                    if ($arr[$k] >= 0xd800 && $arr[$k] <= 0xdfff) {
                        // found a surrogate
                        \trigger_error('utf8_from_unicode: Illegal surrogate ' . 'at index: ' . $k . ', value: ' . $arr[$k], \E_USER_WARNING);
                        return \FALSE;
                        # 3 byte sequence
                    } else {
                        if ($arr[$k] <= 0xffff) {
                            echo \chr(0xe0 | $arr[$k] >> 12);
                            echo \chr(0x80 | $arr[$k] >> 6 & 0x3f);
                            echo \chr(0x80 | $arr[$k] & 0x3f);
                            # 4 byte sequence
                        } else {
                            if ($arr[$k] <= 0x10ffff) {
                                echo \chr(0xf0 | $arr[$k] >> 18);
                                echo \chr(0x80 | $arr[$k] >> 12 & 0x3f);
                                echo \chr(0x80 | $arr[$k] >> 6 & 0x3f);
                                echo \chr(0x80 | $arr[$k] & 0x3f);
                            } else {
                                \trigger_error('utf8_from_unicode: Codepoint out of Unicode range ' . 'at index: ' . $k . ', value: ' . $arr[$k], \E_USER_WARNING);
                                // out of range
                                return \FALSE;
                            }
                        }
                    }
                }
            }
        }
    }
    $result = \ob_get_contents();
    \ob_end_clean();
    return $result;
}

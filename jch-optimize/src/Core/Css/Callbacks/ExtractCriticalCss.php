<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Callbacks;

use CodeAlfa\RegexTokenizer\Debug\Debug;
use DOMXPath;
use JchOptimize\Core\Css\Parser;
use JchOptimize\Core\FeatureHelpers\DynamicSelectors;

use function array_filter;
use function array_unique;
use function defined;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_replace;
use function stripos;
use function strlen;
use function strpos;
use function trim;

defined('_JCH_EXEC') or die('Restricted access');

class ExtractCriticalCss extends AbstractCallback
{
    use Debug;

    public string $sHtmlAboveFold;
    public string $sFullHtml;
    public DOMXPath $oXPath;
    public string $postCss = '';
    public string $preCss = '';
    public bool $isPostProcessing = false;
    protected string $criticalCss = '';

    public function processMatches(array $matches, string $context): string
    {
        $this->_debug($matches[0], $matches[0], 'beginExtractCriticalCss');

        if ($context == 'font-face' || $context == 'keyframes') {
            if (!$this->isPostProcessing) {//If we're not processing font-face or keyframes yet let's just save them for later until after we've done getting all the
                // critical css

                $this->postCss .= $matches[0];

                return '';
            } else {
                if ($context == 'font-face') {
                    preg_match(
                        '#font-family\s*+:\s*+[\'"]?(' . Parser::stringValueToken() . '|[^;}]++)[\'"]?#i',
                        $matches[0],
                        $aM
                    );

                    //Only include fonts in the critical CSS that are being used above the fold
                    //@TODO prevent duplication of fonts in critical css
                    if (!empty($aM[1]) && stripos(
                        $this->criticalCss,
                        $aM[1]
                    ) !== false /*&& ! in_array( $aM[1], $this->aFonts )*/) {
                        //$this->aFonts[] = $aM[1];
                        return $matches[0];
                    } else {
                        return '';
                    }
                }

                $sRule = preg_replace('#@[^\s{]*+\s*+#', '', $matches[2]);

                if (!empty($sRule) && stripos($this->criticalCss, $sRule) !== false) {
                    return $matches[0];
                } else {
                    return '';
                }
            }
        }

        //We'll compile these to prepend to the critical CSS, imported Google font files will never be expanded.
        if ($context == 'import') {
            $this->preCss .= $matches[0];
        }

        //We're only interested in global and conditional css
        if (!in_array($context, array('global', 'media', 'supports', 'document'))) {
            return '';
        }

        if (JCH_PRO) {
            /** @see DynamicSelectors::getDynamicSelectors() */
            if ($this->getContainer()->get(DynamicSelectors::class)->getDynamicSelectors($matches)) {
                $this->appendToCriticalCss($matches[0]);

                $this->_debug('', '', 'afterAddDynamicCss');

                return $matches[0];
            }

            $this->_debug('', '', 'afterSearchDynamicCss');
        }

        $sSelectorGroup = $matches[2];
        //Split selector groups into individual selector chains
        $aSelectorChains = array_filter(explode(',', $sSelectorGroup));
        $aFoundSelectorChains = array();

        //Iterate through each selector chain
        foreach ($aSelectorChains as $sSelectorChain) {
            //If selector chain is a pseudo selector we'll add this group
            if (preg_match("#^:#", $sSelectorChain)) {
                $this->appendToCriticalCss($matches[0]);

                return $matches[0];
            }

            //Remove pseudo-selectors
            $sSelectorChain = preg_replace('#::?[a-zA-Z0-9-]++(\((?>[^()]|(?1))*\))?#', '', $sSelectorChain);
            //If Selector chain is already in critical css just go ahead and add this group
            if (strpos($this->criticalCss, $sSelectorChain) !== false) {
                $this->appendToCriticalCss($matches[0]);

                //Retain matched CSS in combined CSS
                return $matches[0];
            }

            //Check CSS selector chain against HTMl above the fold to find a match
            if ($this->checkCssAgainstHtml($sSelectorChain, $this->sHtmlAboveFold)) {
                //Match found, add selector chain to array
                $aFoundSelectorChains[] = $sSelectorChain;
            }
        }

        //If no valid selector chain was found in the group then we don't add this selector group to the critical CSS
        if (empty($aFoundSelectorChains)) {
            $this->_debug($sSelectorGroup, $matches[0], 'afterSelectorNotFound');

            //Don't add to critical css
            return '';
        }

        //Group the found selector chains
        $sFoundSelectorGroup = implode(',', array_unique($aFoundSelectorChains));
        //remove any backslash used for escaping
        //$sFoundSelectorGroup = str_replace('\\', '', $sFoundSelectorGroup);

        $this->_debug($sFoundSelectorGroup, $matches[0], 'afterSelectorFound');

        $success = null;
        //Convert the selector group to Xpath
        $sXPath = $this->convertCss2XPath($sFoundSelectorGroup, $success);

        $this->_debug($sXPath, $matches[0], 'afterConvertCss2XPath');

        if ($success !== false) {
            $aXPaths = array_unique(explode(' | ', str_replace('\\', '', $sXPath)));

            foreach ($aXPaths as $sXPathValue) {
                $oElement = $this->oXPath->query($sXPathValue);

                //                                if ($oElement === FALSE)
                //                                {
                //                                        echo $aMatches[1] . "\n";
                //                                        echo $sXPath . "\n";
                //                                        echo $sXPathValue . "\n";
                //                                        echo "\n\n";
                //                                }

                //Match found! Add to critical CSS
                if ($oElement !== false && $oElement->length) {
                    $this->appendToCriticalCss($matches[0]);

                    $this->_debug($sXPathValue, $matches[0], 'afterCriticalCssFound');

                    return $matches[0];
                }

                $this->_debug($sXPathValue, $matches[0], 'afterCriticalCssNotFound');
            }
        }

        //No match found for critical CSS.
        return '';
    }

    public function appendToCriticalCss(string $css): void
    {
        $this->criticalCss .= $css;
    }

    /**
     * Do a preliminary simple check to see if a CSS declaration is used by the HTML
     *
     * @param string $selectorChain
     * @param string $html
     *
     * @return   bool   True is all parts of the CSS selector is found in the HTML, false if not
     */
    protected function checkCssAgainstHtml(string $selectorChain, string $html): bool
    {
        //Split selector chain into simple selectors
        $aSimpleSelectors = preg_split(
            '#[^\[ >+]*+(?:\[[^\]]*+\])?\K(?:[ >+]*+|$)#',
            trim($selectorChain),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        //We'll do a quick check first if all parts of each simple selector is found in the HTML
        //Iterate through each simple selector
        foreach ($aSimpleSelectors as $sSimpleSelector) {
            //Match the simple selector into its components
            $sSimpleSelectorRegex = '#([_a-z0-9-]*)(?:([.\#]((?:[_a-z0-9-]|\\\\[^\r\n\f0-9a-z])+))|(\[((?:[_a-z0-9-]|\\\\[^\r\n\f0-9a-z])+)(?:[~|^$*]?=(?|"([^"\]]*+)"|\'([^\'\]]*+)\'|([^\]]*+)))?\]))*#i';
            if (preg_match($sSimpleSelectorRegex, $sSimpleSelector, $aS)) {
                //Elements
                if (!empty($aS[1])) {
                    $sNeedle = '<' . $aS[1];
                    //Just include elements that will be generated by the browser
                    $aDynamicElements = array('<tbody');

                    if (in_array($sNeedle, $aDynamicElements)) {
                        continue;
                    }

                    if (strpos($html, $sNeedle) === false) {
                        //Element part of selector not found,
                        //abort and check next selector chain
                        return false;
                    }
                }

                //Attribute selectors
                if (!empty($aS[4])) {
                    //If the value of the attribute is set we'll look for that
                    //otherwise just look for the attribute
                    $sNeedle = !empty($aS[6]) ? $aS[6] : $aS[5];// . '="';

                    if (!empty($sNeedle) && strpos($html, str_replace('\\', '', $sNeedle)) === false) {
                        //Attribute part of selector not found,
                        //abort and check next selector chain
                        return false;
                    }
                }

                //Ids or Classes
                if (!empty($aS[2])) {
                    $sNeedle = ' ' . $aS[3] . ' ';

                    if (strpos($html, str_replace('\\', '', $sNeedle)) === false) {
                        //The id or class part of selector not found,
                        //abort and check next selector chain
                        return false;
                    }
                }

                //we found this Selector so let's remove it from the chain in case we need to check it
                //against the HTML below the fold
                str_replace($sSimpleSelector, '', $selectorChain);
            }
        }
        //If we get to this point then we've found a simple selector that has all parts in the
        //HTML. Let's save this selector chain and refine its search with Xpath.
        return true;
    }

    /**
     *
     * @param string $sSelector
     * @param bool|null $success
     * @return string
     */
    public function convertCss2XPath(string $sSelector, ?bool &$success = null): ?string
    {
        $sSelector = preg_replace('#\s*([>+~,])\s*#', '$1', $sSelector);
        $sSelector = trim($sSelector);
        $sSelector = preg_replace('#\s+#', ' ', $sSelector);


        if ($sSelector === null) {
            $success = false;
            return null;
        }

        $sSelectorRegex = '#(?!$)'
            . '([>+~, ]?)' //separator
            . '([*_a-z0-9-]*)' //element
            . '(?:(([.\#])((?:[_a-z0-9-]|\\\\[^\r\n\f0-9a-z])+))(([.\#])((?:[_a-z0-9-]|\\\\[^\r\n\f0-9a-z])+))?|'//class or id
            . '(\[((?:[_a-z0-9-]|\\\\[^\r\n\f0-9a-z])+)(([~|^$*]?=)["\']?([^\]"\']+)["\']?)?\]))*' //attribute
            . '#i';

        $result = preg_replace_callback($sSelectorRegex, array($this, '_tokenizer'), $sSelector) . '[1]';

        if ($result === null) {
            $success = false;
            return null;
        }

        return $result;
    }

    /**
     *
     * @param string[] $aM
     *
     * @return string
     */
    protected function _tokenizer(array $aM): string
    {
        $sXPath = '';

        switch ($aM[1]) {
            case '>':
                $sXPath .= '/';

                break;
            case '+':
                $sXPath .= '/following-sibling::*';

                break;
            case '~':
                $sXPath .= '/following-sibling::';

                break;
            case ',':
                $sXPath .= '[1] | descendant-or-self::';

                break;
            case ' ':
                $sXPath .= '/descendant::';

                break;
            default:
                $sXPath .= 'descendant-or-self::';
                break;
        }

        if ($aM[1] != '+') {
            $sXPath .= $aM[2] == '' ? '*' : $aM[2];
        }

        if (isset($aM[3]) || isset($aM[9])) {
            $sXPath .= '[';

            $aPredicates = array();

            if (isset($aM[4]) && $aM[4] == '.') {
                $aPredicates[] = "contains(@class, ' " . $aM[5] . " ')";
            }

            if (isset($aM[7]) && $aM[7] == '.') {
                $aPredicates[] = "contains(@class, ' " . $aM[8] . " ')";
            }

            if (isset($aM[4]) && $aM[4] == '#') {
                $aPredicates[] = "@id = ' " . $aM[5] . " '";
            }

            if (isset($aM[7]) && $aM[7] == '#') {
                $aPredicates[] = "@id = ' " . $aM[8] . " '";
            }

            if (isset($aM[9])) {
                if (!isset($aM[11])) {
                    $aPredicates[] = '@' . $aM[10];
                } else {
                    switch ($aM[12]) {
                        case '=':
                            $aPredicates[] = "@{$aM[10]} = ' {$aM[13]} '";

                            break;
                        case '|=':
                            $aPredicates[] = "(@{$aM[10]} = ' {$aM[13]} ' or "
                                . "starts-with(@{$aM[10]}, ' {$aM[13]}'))";
                            break;
                        case '^=':
                            $aPredicates[] = "starts-with(@{$aM[10]}, ' {$aM[13]}')";
                            break;
                        case '$=':
                            $aPredicates[] = "substring(@{$aM[10]}, string-length(@{$aM[10]})-"
                                . strlen($aM[13]) . ") = '{$aM[13]} '";
                            break;
                        case '~=':
                            $aPredicates[] = "contains(@{$aM[10]}, ' {$aM[13]} ')";
                            break;
                        case '*=':
                            $aPredicates[] = "contains(@{$aM[10]}, '{$aM[13]}')";
                            break;
                        default:
                            break;
                    }
                }
            }

            if ($aM[1] == '+') {
                if ($aM[2] != '') {
                    $aPredicates[] = "(name() = '" . $aM[2] . "')";
                }

                $aPredicates[] = '(position() = 1)';
            }

            $sXPath .= implode(' and ', $aPredicates);
            $sXPath .= ']';
        }

        return $sXPath;
    }
}

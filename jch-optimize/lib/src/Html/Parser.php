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

namespace JchOptimize\Core\Html;

use CodeAlfa\RegexTokenizer\Html;
use Exception;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\Callbacks\AbstractCallback;

use function array_map;
use function array_pop;
use function array_slice;
use function defined;
use function implode;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;

defined('_JCH_EXEC') or die('Restricted access');

class Parser
{
    use Html {
        throwExceptionOnPregError as baseThrowExceptionOnPregError;
    }

    /** @var array         Array of regex of excludes in search */
    protected array $excludes = array();

    /** @var array          Array of ElementObjects containing criteria for elements to search for */
    protected array $aElementObjects = array();

    public function __construct()
    {
    }

    //language=RegExp
    public static function htmlBodyElementToken(): string
    {
        $htmlHead = self::htmlHeadElementToken();

        return "{$htmlHead}\K.*+$";
    }

    //language=RegExp
    public static function htmlHeadElementToken(): string
    {
        $headStartTag = self::htmlStartTagToken('head');
        $htmlString = self::htmlStringToken(['script', 'style', 'template']);
        $headEndTag = self::htmlEndTagToken('head');

        return "{$headStartTag}{$htmlString}?{$headEndTag}";
    }

    //language=RegExp
    public static function htmlClosingHeadTagToken(): string
    {
        $htmlString = self::htmlStringToken(['script', 'style', 'template']);
        $headEndTag = self::htmlEndTagToken('head');

        return "{$htmlString}?\K(?:$headEndTag|$)";
    }

    //language=RegExp
    public static function htmlClosingBodyTagToken(): string
    {
        $bodyEndTag = self::htmlEndTagToken('body');
        $htmlEndTag = self::htmlEndTagToken('html');
        $htmlString = self::htmlStringToken();

        return ".*\K{$bodyEndTag}{$htmlString}?{$htmlEndTag}";
    }

    public function addElementObject(ElementObject $oElementObject): void
    {
        $this->aElementObjects[] = $oElementObject;
    }

    public function addExcludes(array $excludes): void
    {
        foreach ($excludes as $exclude) {
            $this->excludes[] = $exclude;
        }
    }

    /**
     * @throws PregErrorException
     */
    public function processMatchesWithCallback(string $html, CallbackInterface $callbackObject): string
    {
        $regex = $this->getHtmlSearchRegex();

        if ($callbackObject instanceof AbstractCallback) {
            $callbackObject->setRegex($regex);
        }

        $sProcessedHtml = (string)preg_replace_callback(
            '#' . $regex . '#si',
            [$callbackObject, 'processMatches'],
            $html
        );

        self::throwExceptionOnPregError();

        return $sProcessedHtml;
    }

    protected function getHtmlSearchRegex(): string
    {
        $htmlString = self::htmlStringToken($this->excludes);
        $criteriaMatches = $this->processCriteriaMatches();

        return "{$htmlString}?\K(?:{$criteriaMatches}|$)";
    }

    protected function compileCriteria(ElementObject $element): string
    {
        $criteria = '';

        $negAttrCriteriaArray = $element->getNegAttrCriteriaArray();

        if (!empty($negAttrCriteriaArray)) {
            foreach ($negAttrCriteriaArray as $negAttrCriteria) {
                $criteria .= $this->processNegCriteria($negAttrCriteria);
            }
        }

        $posAttrCriteriaArray = $element->getPosAttrCriteriaArray();

        if (!empty($posAttrCriteriaArray)) {
            foreach ($posAttrCriteriaArray as $posAttrCriteria) {
                $criteria .= $this->processPosCriteria($posAttrCriteria);
            }
        }

        $negContentCriteriaArray = $element->getNegContentCriteriaRegex();

        if (!empty($negContentCriteriaArray)) {
            foreach ($negContentCriteriaArray as $negContentCriteria) {
                $criteria .= $this->processNegContentCriteria($negContentCriteria);
            }
        }

        $posContentCriteriaArray = $element->getPosContentCriteriaRegex();

        if (!empty($posContentCriteriaArray)) {
            foreach ($posContentCriteriaArray as $posContentCriteria) {
                $criteria .= $this->processPosContentCriteria($posContentCriteria);
            }
        }

        return $criteria;
    }

    //language=RegExp
    protected function processNegCriteria($criteria): string
    {
        return '(?!' . $this->processCriteria($criteria) . ')';
    }

    protected function processCriteria(string|array $criteria): string
    {
        if (is_array($criteria)) {
            $criteriaRegex = [];
            foreach ($criteria as $criterion) {
                if (isset($criterion['pos'])) {
                    $criteriaRegex[] = $this->processPosCriteria($criterion['pos']);
                }
                if (isset($criterion['neg'])) {
                    $criteriaRegex[] = $this->processNegCriteria($criterion['neg']);
                }
            }

            return implode('', $criteriaRegex);
        }

        $criteriaRegex = $this->translateAttributeCriterion($criteria);
        $attributesList = self::htmlAttributesListToken();
        $genericElement = self::htmlGenericElementNameToken();

        return "<{$genericElement}\b\s++{$attributesList}?$criteriaRegex";
    }

    //language=RegExp
    protected function processPosCriteria($criteria): string
    {
        return '(?=' . $this->processCriteria($criteria) . ')';
    }

    /**
     * @param string $sHtml
     * @param int $flags
     * @return array
     * @throws PregErrorException
     */
    public function findMatches(string $sHtml, int $flags = PREG_PATTERN_ORDER): array
    {
        $regex = $this->getHtmlSearchRegex();
        preg_match_all('#' . $regex . '#si', $sHtml, $aMatches, $flags);

        self::throwExceptionOnPregError();

        //Last array will always be an empty string so let's remove that
        if ($flags == PREG_PATTERN_ORDER) {
            return array_map(function ($a) {
                return array_slice($a, 0, -1);
            }, $aMatches);
        } elseif ($flags == PREG_SET_ORDER) {
            array_pop($aMatches);

            return $aMatches;
        } else {
            return $aMatches;
        }
    }

    /**
     * @throws PregErrorException
     */
    public function removeMatches(string $html): string
    {
        $regex = $this->getHtmlSearchRegex();
        $result = preg_replace("#{$regex}#si", "", $html);

        self::throwExceptionOnPregError();

        return $result;
    }

    protected function translateAttributeCriterion(string $attrCriteriaString): string
    {
        $attrCriteria = explode('||', $attrCriteriaString);
        $criterionRegexArray = [];

        foreach ($attrCriteria as $criterion) {
            if (str_contains($criterion, '!=')) {
                list($name, $value) = explode('!=', $criterion);
                $criterionRegexArray[] = "{$name}\s*+=\s*+(?!\"{$value}\"|'{$value}'|(?<=[=])(?!['\"]){$value}[\s/> ])";
            } elseif (str_contains($criterion, '==')) {
                list($name, $value) = explode('==', $criterion);

                $criterionRegexArray[] = "$name\s*+=\s*+(?:\"{$value}\"|'{$value}'|(?<=[=])(?!['\"]){$value}[\s/> ])";
            } elseif (str_contains($criterion, '~=')) {
                list($name, $value) = explode('~=', $criterion);

                $criterionRegexArray[] = "{$name}\s*+=\s*+(?:\"[^\"]*?(?<=[\" ]){$value}[\" ]|"
                    . "'[^']*?(?<=[' ]){$value}[ ']|(?<==)(?!['\"]){$value}[ />])";
            } elseif (str_contains($criterion, '*=')) {
                list($name, $value) = explode('*=', $criterion);

                $criterionRegexArray[] = "{$name}\s*+=\s*+(?:\"[^\"]*?{$value}|'[^']*?{$value}|(?<==)(?!['\"])[^\s]*?{$value})";
            } else {
                $criterionRegexArray[] = $criterion;
            }
        }

        $criterionRegexString = implode('|', $criterionRegexArray);

        return "(?:$criterionRegexString)";
    }

    private function processCriteriaMatches(): string
    {
        $criteriaMatchesArray = [];

        foreach ($this->aElementObjects as $elementObject) {
            $names = $elementObject->getNamesArray();
            $namesRegex = implode('|', $names);
            $types = "<(?:{$namesRegex})\b";
            $element = "(?=$types)";
            $criteria = $this->compileCriteria($elementObject);
            $match = $this->getElementMatch($elementObject);
            $criteriaMatchesArray[] = "{$element}{$criteria}{$match}";
        }

        $criteriaMatchesString = implode('|', $criteriaMatchesArray);

        return "(?>$criteriaMatchesString)";
    }

    private function getElementMatch(ElementObject $elementObject): string
    {
        $names = $elementObject->getNamesArray();

        if ($elementObject->isNested) {
            $matches = [];
            foreach ($names as $name) {
                $matches[] = self::htmlNestedElementToken($name);

                return implode('|', $matches);
            }
        }

        $namesRegex = '(?:' . implode('|', $names) . ')';

        return $elementObject->voidElementOrStartTagOnly ?
            '(?:' . self::htmlVoidElementToken($namesRegex) . '|' . self::htmlStartTagToken($namesRegex) . ')' :
            self::htmlElementsToken($names);
    }

    private function processNegContentCriteria(mixed $negContentCriteria): string
    {
        return '(?!' . $this->processContentCriteria($negContentCriteria) . ')';
    }

    private function processPosContentCriteria(mixed $posContentCriteria): string
    {
        return '(?=' . $this->processContentCriteria($posContentCriteria) . ')';
    }

    private function processContentCriteria(string $criterion): string
    {
        $startTag = Parser::htmlStartTagToken();
        $endTag = Parser::htmlEndTagToken();

        $textContent = "(?>(?!{$endTag}).)*";

        return "{$startTag}{$textContent}?(?>{$criterion})";
    }

    /**
     * @throws PregErrorException
     */
    public static function throwExceptionOnPregError(): void
    {
        try {
            self::baseThrowExceptionOnPregError();
        } catch (Exception $exception) {
            throw new PregErrorException($exception->getMessage());
        }
    }
}

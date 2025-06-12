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

namespace JchOptimize\Core\Css;

use CodeAlfa\RegexTokenizer\Css;
use Exception;
use JchOptimize\Core\Css\Callbacks\AbstractCallback;
use JchOptimize\Core\Exception\PregErrorException;

use function defined;
use function implode;
use function preg_replace;
use function preg_replace_callback;

defined('_JCH_EXEC') or die('Restricted access');

class Parser
{
    use Css {
        throwExceptionOnPregError as baseThrowExceptionOnPregError;
    }

    protected array $cssSearchObject = [];

    protected bool $onlyMatchAtRules = false;

    //language=RegExp
    public static function cssInvalidCssToken(): string
    {
        return '(?:@[^\r\n]*+$|[^;}@]*+(?:[;}@]|$))';
    }

    public function matchOnlyAtRules(): void
    {
        $this->onlyMatchAtRules = true;
    }

    /**
     * @throws PregErrorException
     */
    public function processMatchesWithCallback(
        string $sCss,
        AbstractCallback $callback
    ): string {
        $regex = $this->getCssSearchRegex();

        $callback->setParser($this);

        $sProcessedCss = preg_replace_callback(
            '#' . $regex . '#six',
            [$callback, 'processMatches'],
            $sCss
        );

        self::throwExceptionOnPregError();

        return $sProcessedCss;
    }

    protected function getCssSearchRegex(): string
    {
        $cssString = $this->getCssString();
        $criteriaMatches = $this->processCriteriaMatches();

        return "{$cssString}?\K(?:$criteriaMatches|$)";
    }

    /**
     * @throws PregErrorException
     */
    public function replaceMatches(string $css, string $replace): string
    {
        $processedCss = preg_replace('#' . $this->getCssSearchRegex() . '#i', $replace, $css);

        self::throwExceptionOnPregError();

        if (is_string($processedCss)) {
            return $processedCss;
        }

        throw new PregErrorException('Unknown error processing regex');
    }

    public function setCssSearchObject(CssSearchObject $cssSearchObject): void
    {
        $this->cssSearchObject[] = $cssSearchObject;
    }

    private function processCriteriaMatches(): string
    {
        $criteriaMatchesArray = [];

        /** @var CssSearchObject $searchObject */
        foreach ($this->cssSearchObject as $searchObject) {
            $criteria = $this->compileCriteria($searchObject);
            $match = $searchObject->getCssMatch();

            $criteriaMatchesArray[] = "{$criteria}{$match}";
        }

        $criteriaMatchesString = implode('|', $criteriaMatchesArray);

        return "(?>{$criteriaMatchesString})";
    }

    private function compileCriteria(CssSearchObject $searchObject): string
    {
        $searchCriteria = '';

        $atRuleCriteria = $searchObject->getCssAtRuleCriteria();

        if (!empty($atRuleCriteria)) {
            foreach ($atRuleCriteria as $atRuleCriterion) {
                $searchCriteria .= $this->processAtRuleCriteria($atRuleCriterion);
            }
        }

        $cssRuleCriteria = $searchObject->getCssRuleCriteria();

        if (!empty($cssRuleCriteria)) {
            foreach ($cssRuleCriteria as $cssRuleCriterion) {
                $searchCriteria .= $this->processCssRuleCriteria($cssRuleCriterion);
            }
        }

        $cssMatchCriteria = $searchObject->getCssMatchCriteria();

        if (!empty($cssMatchCriteria)) {
            foreach ($cssMatchCriteria as $cssMatchCriterion) {
                $searchCriteria .= $this->processCssMatchCriteria($cssMatchCriterion);
            }
        }

        return $searchCriteria;
    }

    private function processCssRuleCriteria(string|array $cssRuleCriterion): string
    {
        $selectors = self::cssSelectorListToken();
        $bc = self::blockCommentToken();

        if (is_array($cssRuleCriterion)) {
            $st =  key($cssRuleCriterion);
            $criterion = current($cssRuleCriterion);
        } else {
            $st = '';
            $criterion = $cssRuleCriterion;
        }

        return "(?={$selectors}{(?>[^}/{$st}]++|{$bc}|[{$st}/])*?{$criterion})";
    }

    private function processAtRuleCriteria(string|array $atRuleCriterion): string
    {
        $bc = self::blockCommentToken();

        if (is_array($atRuleCriterion)) {
            $st = key($atRuleCriterion);
            $criterion = current($atRuleCriterion);
        } else {
            $st = '';
            $criterion = $atRuleCriterion;
        }

        return "(?=@(?:-[^-]++-)?[a-zA-Z-]++\s(?>[^{}/;@{$st}]++|{$bc}|[{$st}/])*?{$criterion})";
    }

    private function processCssMatchCriteria(string $cssMatchCriterion): string
    {
        return "$cssMatchCriterion";
    }

    private function getCssString(): string
    {
        if ($this->onlyMatchAtRules) {
            $bc = self::blockCommentToken();

            return "(?>[^@/]++|{$bc}|/++|@++)*";
        }

        return self::cssStringToken();
    }

    public static function throwExceptionOnPregError(): void
    {
        try {
            self::baseThrowExceptionOnPregError();
        } catch (Exception $exception) {
            throw new PregErrorException($exception->getMessage());
        }
    }
}

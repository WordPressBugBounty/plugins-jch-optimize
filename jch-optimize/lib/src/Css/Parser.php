<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css;

use CodeAlfa\RegexTokenizer\Css;
use JchOptimize\Core\Css\Callbacks\AbstractCallback;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Exception\PregErrorException;

\defined('_JCH_EXEC') or exit('Restricted access');
class Parser
{
    use Css;
    protected array $aExcludes = [];
    protected ?\JchOptimize\Core\Css\CssSearchObject $cssSearchObject = null;
    protected bool $bBranchReset = \true;
    protected string $sParseTerm = '\\s*+';
    protected static int $subroutines = 0;

    public function __construct()
    {
        $this->aExcludes = [
            self::blockCommentToken(),
            self::lineCommentToken(),
            self::cssRuleWithCaptureValueToken(),
            self::cssAtRulesToken(),
            self::cssNestedAtRulesWithCaptureValueToken(),
            // Custom exclude
            '\\|"(?>[^"{}]*+"?)*?[^"{}]*+"\\|',
            self::cssInvalidCssToken(),
        ];
    }

    // language=RegExp
    public static function cssRuleWithCaptureValueToken(bool $bCaptureValue = \false, string $sCriteria = ''): string
    {
        $sCssRule = '<<(?<=^|[{}/\\s;|])[^@/\\s{}]'.self::parseNoStrings().'>>\\{'.$sCriteria.'<<'.self::parse().'>>\\}';

        return self::prepare($sCssRule, $bCaptureValue);
    }

    // language=RegExp
    public static function cssAtRulesToken(): string
    {
        return '@\\w++\\b\\s++(?:'.self::cssIdentToken().')?(?:'.self::stringWithCaptureValueToken().'|'.self::cssUrlWithCaptureValueToken().')[^;]*+;';
    }

    // language=RegExp
    /**
     * @param (mixed|string)[] $atRulesArray
     *
     * @psalm-param list{0?: 'font-face'|'media'|mixed, 1?: 'keyframes'|mixed, 2?: 'page'|mixed, 3?: 'font-feature-values'|mixed, 4?: 'counter-style'|mixed, 5?: 'viewport'|mixed, 6?: 'property'|mixed,...} $atRulesArray
     */
    public static function cssNestedAtRulesWithCaptureValueToken(array $atRulesArray = [], bool $shouldCaptureValue = \false, bool $empty = \false): string
    {
        $atRulesString = !empty($atRulesArray) ? '(?>'.\implode('|', $atRulesArray).')' : '';
        $i = self::$subroutines++;
        $sValue = $empty ? '\\s*+' : '(?>'.self::parse('', \true)."|(?P>css{$i}))*+";
        $atRulesString = "<<@(?:-[^-]++-)??{$atRulesString}[^{};]*+>>(?P<css{$i}>\\{<<{$sValue}>>\\})";

        return self::prepare($atRulesString, $shouldCaptureValue);
    }

    // language=RegExp
    public static function cssInvalidCssToken(): string
    {
        return '[^;}@\\r\\n]*+[;}@\\r\\n]';
    }

    // language=RegExp
    public static function cssAtImportWithCaptureValueToken(bool $bCV = \false): string
    {
        $sAtImport = '@import\\s++<<<'.self::stringWithCaptureValueToken($bCV).'|'.self::cssUrlWithCaptureValueToken($bCV).'>>><<[^;]*+>>;';

        return self::prepare($sAtImport, $bCV);
    }

    // language=RegExp
    public static function cssAtFontFaceWithCaptureValueToken($sCaptureValue = \false): string
    {
        return self::cssNestedAtRulesWithCaptureValueToken(['font-face'], $sCaptureValue);
    }

    // language=RegExp
    public static function cssAtMediaWithCaptureValueToken($sCaptureValue = \false): string
    {
        return self::cssNestedAtRulesWithCaptureValueToken(['media'], $sCaptureValue);
    }

    // language=RegExp
    public static function cssAtCharsetWithCaptureValueToken($sCaptureValue = \false): string
    {
        return '@charset\\s++'.self::stringWithCaptureValueToken($sCaptureValue).'[^;]*+;';
    }

    // language=RegExp
    public static function cssAtNameSpaceToken(): string
    {
        return '@namespace\\s++(?:'.self::cssIdentToken().')?(?:'.self::stringWithCaptureValueToken().'|'.self::cssUrlWithCaptureValueToken().')[^;]*+;';
    }

    // language=RegExp
    public static function cssStatementsToken(): string
    {
        return '(?:'.self::cssRuleWithCaptureValueToken().'|'.self::cssAtRulesToken().'|'.self::cssNestedAtRulesWithCaptureValueToken().')';
    }

    // language=RegExp
    public static function cssMediaTypesToken(): string
    {
        return '(?>all|screen|print|speech|aural|tv|tty|projection|handheld|braille|embossed)';
    }

    public function disableBranchReset(): void
    {
        $this->bBranchReset = \false;
    }

    public function setExcludesArray($aExcludes): void
    {
        $this->aExcludes = $aExcludes;
    }

    /**
     * @throws Exception\PregErrorException
     */
    public function processMatchesWithCallback(string $sCss, AbstractCallback $oCallback, string $sContext = 'global'): ?string
    {
        $sRegex = $this->getCssSearchRegex();
        $sProcessedCss = \preg_replace_callback('#'.$sRegex.'#six', function ($aMatches) use ($oCallback, $sContext): string {
            if (empty(\trim($aMatches[0]))) {
                return $aMatches[0];
            }
            if (\str_starts_with($aMatches[0], '@')) {
                $sContext = $this->getContext($aMatches[0]);
                foreach ($this->getCssSearchObject()->getCssNestedRuleNames() as $aAtRule) {
                    if ($aAtRule['name'] == $sContext) {
                        if ($aAtRule['recurse']) {
                            return $aMatches[2].'{'.$this->processMatchesWithCallback($aMatches[4], $oCallback, $sContext).'}';
                        }

                        return $oCallback->processMatches($aMatches, $sContext);
                    }
                }
            }

            return $oCallback->processMatches($aMatches, $sContext);
        }, $sCss);

        try {
            self::throwExceptionOnPregError();
        } catch (\Exception $exception) {
            throw new Exception\PregErrorException($exception->getMessage());
        }

        return $sProcessedCss;
    }

    /**
     * @throws PregErrorException
     */
    public function replaceMatches(string $css, string $replace): ?string
    {
        $processedCss = \preg_replace('#'.$this->getCssSearchRegex().'#i', $replace, $css);

        try {
            self::throwExceptionOnPregError();
        } catch (\Exception $exception) {
            throw new Exception\PregErrorException($exception->getMessage());
        }
        if (\is_string($processedCss)) {
            return $processedCss;
        }

        throw new PregErrorException('Unknown error processing regex');
    }

    public function setCssSearchObject(CssSearchObject $cssSearchObject): void
    {
        $this->cssSearchObject = $cssSearchObject;
    }

    // language=RegExp
    public function setExcludes(array $aExcludes): void
    {
        $this->aExcludes = $aExcludes;
    }

    public function setParseTerm(string $sParseTerm): void
    {
        $this->sParseTerm = $sParseTerm;
    }

    // language=RegExp
    protected static function parseNoStrings(): string
    {
        return '(?>(?:[^{}/]++|/)(?>'.self::blockCommentToken().')?)*?';
    }

    // language=RegExp
    /**
     * @psalm-param '' $include
     */
    protected static function parse(string $include = '', bool $noEmpty = \false): string
    {
        $repeat = $noEmpty ? '+' : '*';

        return "(?>[^{}\"'/{$include}]{$repeat}+(?>".self::blockCommentToken().'|'.self::stringWithCaptureValueToken()."|/)*+){$repeat}?";
    }

    // language=RegExp
    protected static function _parseCss($sInclude = '', $bNoEmpty = \false): string
    {
        return self::parse($sInclude, $bNoEmpty);
    }

    protected function getCssSearchRegex(): string
    {
        return $this->parseCss($this->getExcludes()).'\\K(?:'.$this->getCriteria().'|$)';
    }

    protected function parseCSS($aExcludes = []): string
    {
        if (!empty($aExcludes)) {
            $aExcludes = '(?>'.\implode('|', $aExcludes).')?';
        } else {
            $aExcludes = '';
        }

        return '(?>'.$this->sParseTerm.$aExcludes.')*?'.$this->sParseTerm;
    }

    protected function getExcludes(): array
    {
        return $this->aExcludes;
    }

    protected function getCriteria(): string
    {
        $oObj = $this->getCssSearchObject();
        $aCriteria = [];
        // We need to add Nested Rules criteria first to avoid trouble with recursion and branch capture reset
        $aNestedRules = $oObj->getCssNestedRuleNames();
        if (!empty($aNestedRules)) {
            if (1 == \count($aNestedRules) && \true == $aNestedRules[0]['empty-value']) {
                $aCriteria[] = self::cssNestedAtRulesWithCaptureValueToken([$aNestedRules[0]['name']], \false, \true);
            } elseif (1 == \count($aNestedRules) && '*' == $aNestedRules[0]['name']) {
                $aCriteria[] = self::cssNestedAtRulesWithCaptureValueToken([]);
            } else {
                $aCriteria[] = self::cssNestedAtRulesWithCaptureValueToken(\array_column($aNestedRules, 'name'), \true);
            }
        }
        $aAtRules = $oObj->getCssAtRuleCriteria();
        if (!empty($aAtRules)) {
            $aCriteria[] = '('.\implode('|', $aAtRules).')';
        }
        $aCssRules = $oObj->getCssRuleCriteria();
        if (!empty($aCssRules)) {
            if (1 == \count($aCssRules) && '.' == $aCssRules[0]) {
                $aCriteria[] = self::cssRuleWithCaptureValueToken(\true);
            } elseif (1 == \count($aCssRules) && '*' == $aCssRules[0]) {
                // Array of nested rules we don't want to recurse in
                $aNestedRules = ['font-face', 'keyframes', 'page', 'font-feature-values', 'counter-style', 'viewport', 'property'];
                $aCriteria[] = '(?:(?:'.self::cssRuleWithCaptureValueToken().'\\s*+|'.self::blockCommentToken().'\\s*+|'.self::cssNestedAtRulesWithCaptureValueToken($aNestedRules).'\\s*+)++)';
            } else {
                $sStr = self::getParseStr($aCssRules);
                $sRulesCriteria = '(?=(?>['.$sStr.']?[^{}'.$sStr.']*+)*?('.\implode('|', $aCssRules).'))';
                $aCriteria[] = self::cssRuleWithCaptureValueToken(\true, $sRulesCriteria);
            }
        }
        $aCssCustomRules = $oObj->getCssCustomRule();
        if (!empty($aCssCustomRules)) {
            $aCriteria[] = '('.\implode('|', $aCssCustomRules).')';
        }

        return ($this->bBranchReset ? '(?|' : '(?:').\implode('|', $aCriteria).')';
    }

    // language=RegExp
    protected static function getParseStr(array $aExcludes): string
    {
        $aStr = [];
        foreach ($aExcludes as $sExclude) {
            $sSubStr = \substr($sExclude, 0, 1);
            if (!\in_array($sSubStr, $aStr)) {
                $aStr[] = $sSubStr;
            }
        }

        return \implode('', $aStr);
    }

    protected function getContext(string $sMatch): string
    {
        \preg_match('#^@(?:-[^-]+-)?([^\\s{(]++)#i', $sMatch, $aMatches);

        return !empty($aMatches[1]) ? \strtolower($aMatches[1]) : 'global';
    }

    protected function getCssSearchObject(): CssSearchObject
    {
        if ($this->cssSearchObject instanceof \JchOptimize\Core\Css\CssSearchObject) {
            return $this->cssSearchObject;
        }

        throw new Exception\PropertyNotFoundException('CssSearchObject not set in '.\get_class($this));
    }
}

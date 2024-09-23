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
use JchOptimize\Core\Exception;

use function array_column;
use function count;
use function defined;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function strtolower;
use function substr;
use function trim;

defined('_JCH_EXEC') or die('Restricted access');

class Parser
{
    use Css;

    protected array $aExcludes = [];

    /** @var CssSearchObject */
    protected CssSearchObject $oCssSearchObject;

    protected bool $bBranchReset = true;

    protected string $sParseTerm = '\s*+';

    public function __construct()
    {
        $this->aExcludes = [
            self::blockCommentToken(),
            self::lineCommentToken(),
            self::cssRuleWithCaptureValueToken(),
            self::cssAtRulesToken(),
            self::cssNestedAtRulesWithCaptureValueToken(),
            //Custom exclude
            '\|"(?>[^"{}]*+"?)*?[^"{}]*+"\|',
            self::cssInvalidCssToken()
        ];
    }

    //language=RegExp
    public static function cssRuleWithCaptureValueToken(bool $bCaptureValue = false, string $sCriteria = ''): string
    {
        $sCssRule = '<<(?<=^|[{}/\s;|])[^@/\s{}]' . self::parseNoStrings() . '>>\{' . $sCriteria . '<<' . self::parse() . '>>\}';

        return self::prepare($sCssRule, $bCaptureValue);
    }

    //language=RegExp
    protected static function parseNoStrings(): string
    {
        return '(?>(?:[^{}/]++|/)(?>' . self::blockCommentToken() . ')?)*?';
    }

    //language=RegExp

    /**
     * @psalm-param '' $sInclude
     */
    protected static function parse(string $sInclude = '', bool $bNoEmpty = false): string
    {
        $sRepeat = $bNoEmpty ? '+' : '*';

        return '(?>(?:[^{}"\'/' . $sInclude . ']++|/)(?>' . self::blockCommentToken() . '|' . self::stringWithCaptureValueToken() . ')?)' . $sRepeat . '?';
    }

    //language=RegExp
    public static function cssAtRulesToken(): string
    {
        return '@\w++\b\s++(?:' . self::cssIdentToken() . ')?' . '(?:' . self::stringWithCaptureValueToken() . '|' . self::cssUrlWithCaptureValueToken() . ')[^;]*+;';
    }

    //language=RegExp

    /**
     * @param (mixed|string)[] $aAtRules
     *
     * @psalm-param list{0?: 'font-face'|'media'|mixed, 1?: 'keyframes'|mixed, 2?: 'page'|mixed, 3?: 'font-feature-values'|mixed, 4?: 'counter-style'|mixed, 5?: 'viewport'|mixed, 6?: 'property'|mixed,...} $aAtRules
     */
    public static function cssNestedAtRulesWithCaptureValueToken(
        array $aAtRules = [],
        bool  $bCV = false,
        bool  $bEmpty = false
    ): string {
        $sAtRules = !empty($aAtRules) ? '(?>' . implode('|', $aAtRules) . ')' : '';

        $iN = $bCV ? 2 : 1;
        $sValue = $bEmpty ? '\s*+' : '(?>' . self::parse('', true) . '|(?-' . $iN . '))*+';

        $sAtRules = '<<@(?:-[^-]++-)??' . $sAtRules . '[^{};]*+>>(\{<<' . $sValue . '>>\})';

        return self::prepare($sAtRules, $bCV);
    }

    /**
     * @return string
     */
    //language=RegExp
    public static function cssInvalidCssToken(): string
    {
        return '[^;}@\r\n]*+[;}@\r\n]';
    }

    //language=RegExp
    public static function cssAtImportWithCaptureValueToken(bool $bCV = false): string
    {
        $sAtImport = '@import\s++<<<' . self::stringWithCaptureValueToken(
            $bCV
        ) . '|' . self::cssUrlWithCaptureValueToken($bCV) . '>>><<[^;]*+>>;';

        return self::prepare($sAtImport, $bCV);
    }

    //language=RegExp
    public static function cssAtFontFaceWithCaptureValueToken($sCaptureValue = false): string
    {
        return self::cssNestedAtRulesWithCaptureValueToken(['font-face'], $sCaptureValue);
    }

    //language=RegExp
    public static function cssAtMediaWithCaptureValueToken($sCaptureValue = false): string
    {
        return self::cssNestedAtRulesWithCaptureValueToken(['media'], $sCaptureValue);
    }

    //language=RegExp
    public static function cssAtCharsetWithCaptureValueToken($sCaptureValue = false): string
    {
        return '@charset\s++' . self::stringWithCaptureValueToken($sCaptureValue) . '[^;]*+;';
    }

    //language=RegExp
    public static function cssAtNameSpaceToken(): string
    {
        return '@namespace\s++' . '(?:' . self::cssIdentToken() . ')?' . '(?:' . self::stringWithCaptureValueToken() . '|' . self::cssUrlWithCaptureValueToken() . ')[^;]*+;';
    }

    //language=RegExp
    public static function cssStatementsToken(): string
    {
        return '(?:' . self::cssRuleWithCaptureValueToken() . '|' . self::cssAtRulesToken() . '|' . self::cssNestedAtRulesWithCaptureValueToken() . ')';
    }

    //language=RegExp
    public static function cssMediaTypesToken(): string
    {
        return '(?>all|screen|print|speech|aural|tv|tty|projection|handheld|braille|embossed)';
    }

    //language=RegExp
    protected static function _parseCss($sInclude = '', $bNoEmpty = false): string
    {
        return self::parse($sInclude, $bNoEmpty);
    }

    public function disableBranchReset(): void
    {
        $this->bBranchReset = false;
    }

    public function setExcludesArray($aExcludes): void
    {
        $this->aExcludes = $aExcludes;
    }

    /**
     * @param Callbacks\CombineMediaQueries|Callbacks\CorrectUrls|Callbacks\ExtractCriticalCss|Callbacks\FormatCss|Callbacks\HandleAtRules $oCallback
     *
     * @return null|string
     * @throws Exception\PregErrorException
     *
     */
    public function processMatchesWithCallback(string $sCss, $oCallback, string $sContext = 'global'): ?string
    {
        $sRegex = $this->getCssSearchRegex();

        $sProcessedCss = preg_replace_callback(
            '#' . $sRegex . '#six',
            function ($aMatches) use ($oCallback, $sContext): string {
                if (empty(trim($aMatches[0]))) {
                    return $aMatches[0];
                }

                if (substr($aMatches[0], 0, 1) == '@') {
                    $sContext = $this->getContext($aMatches[0]);

                    foreach ($this->oCssSearchObject->getCssNestedRuleNames() as $aAtRule) {
                        if ($aAtRule['name'] == $sContext) {
                            if ($aAtRule['recurse']) {
                                return $aMatches[2] . '{' . $this->processMatchesWithCallback(
                                    $aMatches[4],
                                    $oCallback,
                                    $sContext
                                ) . '}';
                            } else {
                                return $oCallback->processMatches($aMatches, $sContext);
                            }
                        }
                    }
                }

                return $oCallback->processMatches($aMatches, $sContext);
            },
            $sCss
        );

        try {
            self::throwExceptionOnPregError();
        } catch (\Exception $exception) {
            throw new Exception\PregErrorException($exception->getMessage());
        }

        return $sProcessedCss;
    }

    protected function getCssSearchRegex(): string
    {
        $sRegex = $this->parseCss($this->getExcludes()) . '\K(?:' . $this->getCriteria() . '|$)';

        return $sRegex;
    }

    protected function parseCSS($aExcludes = []): string
    {
        if (!empty($aExcludes)) {
            $aExcludes = '(?>' . implode('|', $aExcludes) . ')?';
        } else {
            $aExcludes = '';
        }

        return '(?>' . $this->sParseTerm . $aExcludes . ')*?' . $this->sParseTerm;
    }

    protected function getExcludes(): array
    {
        return $this->aExcludes;
    }

    protected function getCriteria(): string
    {
        $oObj = $this->oCssSearchObject;

        $aCriteria = [];

        //We need to add Nested Rules criteria first to avoid trouble with recursion and branch capture reset
        $aNestedRules = $oObj->getCssNestedRuleNames();

        if (!empty($aNestedRules)) {
            if (count($aNestedRules) == 1 && $aNestedRules[0]['empty-value'] == true) {
                $aCriteria[] = self::cssNestedAtRulesWithCaptureValueToken(
                    [$aNestedRules[0]['name']],
                    false,
                    true
                );
            } elseif (count($aNestedRules) == 1 && $aNestedRules[0]['name'] == '*') {
                $aCriteria[] = self::cssNestedAtRulesWithCaptureValueToken([]);
            } else {
                $aCriteria[] = self::cssNestedAtRulesWithCaptureValueToken(array_column($aNestedRules, 'name'), true);
            }
        }

        $aAtRules = $oObj->getCssAtRuleCriteria();

        if (!empty($aAtRules)) {
            $aCriteria[] = '(' . implode('|', $aAtRules) . ')';
        }

        $aCssRules = $oObj->getCssRuleCriteria();

        if (!empty($aCssRules)) {
            if (count($aCssRules) == 1 && $aCssRules[0] == '.') {
                $aCriteria[] = self::cssRuleWithCaptureValueToken(true);
            } elseif (count($aCssRules) == 1 && $aCssRules[0] == '*') {
                //Array of nested rules we don't want to recurse in
                $aNestedRules = [
                    'font-face',
                    'keyframes',
                    'page',
                    'font-feature-values',
                    'counter-style',
                    'viewport',
                    'property'
                ];
                $aCriteria[] = '(?:(?:' . self::cssRuleWithCaptureValueToken() . '\s*+|' . self::blockCommentToken() . '\s*+|' . self::cssNestedAtRulesWithCaptureValueToken($aNestedRules) . '\s*+)++)';
            } else {
                $sStr = self::getParseStr($aCssRules);

                $sRulesCriteria = '(?=(?>[' . $sStr . ']?[^{}' . $sStr . ']*+)*?(' . implode('|', $aCssRules) . '))';

                $aCriteria[] = self::cssRuleWithCaptureValueToken(true, $sRulesCriteria);
            }
        }

        $aCssCustomRules = $oObj->getCssCustomRule();

        if (!empty($aCssCustomRules)) {
            $aCriteria[] = '(' . implode('|', $aCssCustomRules) . ')';
        }

        return ($this->bBranchReset ? '(?|' : '(?:') . implode('|', $aCriteria) . ')';
    }

    //language=RegExp
    protected static function getParseStr(array $aExcludes): string
    {
        $aStr = [];

        foreach ($aExcludes as $sExclude) {
            $sSubStr = substr($sExclude, 0, 1);

            if (!in_array($sSubStr, $aStr)) {
                $aStr[] = $sSubStr;
            }
        }

        return implode('', $aStr);
    }

    protected function getContext(string $sMatch): string
    {
        preg_match('#^@(?:-[^-]+-)?([^\s{(]++)#i', $sMatch, $aMatches);

        return !empty($aMatches[1]) ? strtolower($aMatches[1]) : 'global';
    }


    /**
     * @return null|string
     * @throws Exception\PregErrorException
     *
     * @psalm-param '' $sReplace
     *
     */
    public function replaceMatches($sCss, string $sReplace): ?string
    {
        $sProcessedCss = preg_replace('#' . $this->getCssSearchRegex() . '#i', $sReplace, $sCss);

        try {
            self::throwExceptionOnPregError();
        } catch (\Exception $exception) {
            throw new Exception\PregErrorException($exception->getMessage());
        }

        return $sProcessedCss;
    }

    public function setCssSearchObject(CssSearchObject $oCssSearchObject): void
    {
        $this->oCssSearchObject = $oCssSearchObject;
    }

    //language=RegExp
    public function setExcludes(array $aExcludes): void
    {
        $this->aExcludes = $aExcludes;
    }

    public function setParseTerm(string $sParseTerm): void
    {
        $this->sParseTerm = $sParseTerm;
    }
}

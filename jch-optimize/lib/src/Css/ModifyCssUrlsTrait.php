<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css;

use JchOptimize\Core\Css\Components\CssUrl;
use JchOptimize\Core\Exception\InvalidArgumentException;

use function preg_match;
use function preg_replace;
use function preg_replace_callback;

trait ModifyCssUrlsTrait
{
    protected bool $modified = false;

    protected function internalModifyCssUrls(ModifyCssUrlsProcessor $cssUrlProcessor, string $css): string
    {
        $regex = self::cssUrlStringToken();

        $css = preg_replace_callback("#{$regex}#i", function ($matches) use ($cssUrlProcessor, $css) {
            if (empty($matches[0])) {
                return $matches[0];
            }

            try {
                $cssUrl = CssUrl::load($matches[0]);
            } catch (InvalidArgumentException) {
                return $matches[0];
            }

            $cssUrl->setImportantContext($this->evaluateImportantContext($css));

            $modifiedCssUrl = $cssUrlProcessor->processCssUrls($cssUrl);

            if ($modifiedCssUrl !== $cssUrl) {
                $this->modified = true;
            }

            if ($modifiedCssUrl === null) {
                return '';
            }

            return $modifiedCssUrl->render();
        }, $css);

        //Remove any empty background declarations
        return preg_replace(
            '#background(?:-image)?\s*+:\s*+(?:!important)?\s*+(?:;\s*+|(?=(?:}|$)))#',
            '',
            $css
        );
    }

    protected static function cssUrlStringToken(): string
    {
        $url = Parser::cssUrlToken();
        $bc = Parser::blockCommentToken();
        $esc = Parser::cssEscapedString();
        $dqStr = Parser::doubleQuoteStringToken();
        $sqStr = Parser::singleQuoteStringToken();
        $cssBlock = Parser::cssBlockToken();

        return "(?>[^u/'\"\\\\{}]++|{$bc}|{$esc}|{$dqStr}|{$sqStr}|{$cssBlock}|[/u]++)*?\K(?:{$url}|$)";
    }

    private function evaluateImportantContext(string $cssDeclaration): bool
    {
        return (bool) preg_match('#background[^:]*?:[^;}]*?!important#i', $cssDeclaration);
    }
}

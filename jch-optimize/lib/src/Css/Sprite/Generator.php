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

namespace JchOptimize\Core\Css\Sprite;

use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Cdn;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Profiler;

\defined('_JCH_EXEC') or exit('Restricted access');
class Generator implements LoggerAwareInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    /**
     * @var null|Controller
     */
    private ?\JchOptimize\Core\Css\Sprite\Controller $spriteController;

    private Registry $params;

    public function __construct(Registry $params, ?Controller $spriteController)
    {
        $this->params = $params;
        $this->spriteController = $spriteController;
    }

    /**
     * Grabs background images with no-repeat attribute from css and merge them in one file called a sprite.
     * Css is updated with sprite url and correct background positions for affected images.
     *
     * @param string $sCss Aggregated css file before sprite generation
     */
    public function getSprite(string $sCss): array
    {
        $aMatches = $this->processCssUrls($sCss);
        if (empty($aMatches)) {
            return [];
        }
        if (\is_null($this->spriteController)) {
            return [];
        }

        return $this->generateSprite($aMatches);
    }

    /**
     * Uses regex to find css declarations containing background images to include in sprite.
     *
     * @param string $css       Aggregated css file
     * @param bool   $isBackend True if running in admin
     *
     * @return array Array of css declarations and image urls to replace with sprite
     *
     * @throws Exception\RuntimeException
     */
    public function processCssUrls(string $css, bool $isBackend = \false): array
    {
        JCH_DEBUG ? Profiler::start('ProcessCssUrls') : null;
        $aRegexStart = [];
        $aRegexStart[0] = '
                        #(?:{
                                (?=\\s*+(?>[^}\\s:]++[\\s:]++)*?url\\(
                                        (?=[^)]+\\.(?:png|gif|jpe?g))
                                ([^)]+)\\))';
        $aRegexStart[1] = '
                        (?=\\s*+(?>[^}\\s:]++[\\s:]++)*?no-repeat)';
        $aRegexStart[2] = '
                        (?(?=\\s*(?>[^};]++[;\\s]++)*?background(?:-position)?\\s*+:\\s*+(?:[^;}\\s]++[^}\\S]++)*?
                                (?<p>(?:[tblrc](?:op|ottom|eft|ight|enter)|-?[0-9]+(?:%|[c-x]{2})?))(?:\\s+(?&p))?)
                                        (?=\\s*(?>[^};]++[;\\s]++)*?background(?:-position)?\\s*+:\\s*+(?>[^;}\\s]++[\\s]++)*?
                                                (?:left|top|0(?:%|[c-x]{2})?)\\s+(?:left|top|0(?:%|[c-x]{2})?)
                                        )
                        )';
        $sRegexMiddle = '   
                        [^{}]++} )';
        $sRegexEnd = '#isx';
        $aIncludeImages = Helper::getArray($this->params->get('csg_include_images', ''));
        $aExcludeImages = Helper::getArray($this->params->get('csg_exclude_images', ''));
        $sIncImagesRegex = '';
        if (!empty($aIncludeImages[0])) {
            foreach ($aIncludeImages as &$sImage) {
                $sImage = \preg_quote($sImage, '#');
            }
            $sIncImagesRegex .= '
                                |(?:{
                                        (?=\\s*+(?>[^}\\s:]++[\\s:]++)*?url';
            $sIncImagesRegex .= ' (?=[^)]* [/(](?:'.\implode('|', $aIncludeImages).')\\))';
            $sIncImagesRegex .= '\\(([^)]+)\\)
                                        )
                                        [^{}]++ })';
        }
        $sExImagesRegex = '';
        if (!empty($aExcludeImages[0])) {
            $sExImagesRegex .= '(?=\\s*+(?>[^}\\s:]++[\\s:]++)*?url\\(
                                                        [^)]++  (?<!';
            foreach ($aExcludeImages as &$sImage) {
                $sImage = \preg_quote($sImage, '#');
            }
            $sExImagesRegex .= \implode('|', $aExcludeImages).')\\)
                                                        )';
        }
        $sRegexStart = \implode('', $aRegexStart);
        $sRegex = $sRegexStart.$sExImagesRegex.$sRegexMiddle.$sIncImagesRegex.$sRegexEnd;
        if (\false === \preg_match_all($sRegex, $css, $aMatches)) {
            throw new Exception\RuntimeException('Error occurred matching for images to sprite');
        }
        if (isset($aMatches[3])) {
            $total = \count($aMatches[1]);
            for ($i = 0; $i < $total; ++$i) {
                $aMatches[1][$i] = \trim($aMatches[1][$i]) ? $aMatches[1][$i] : $aMatches[3][$i];
            }
        }
        if ($isBackend) {
            if (\is_null($this->spriteController)) {
                return ['include' => [], 'exclude' => []];
            }
            $aImages = [];
            $aImagesMatches = [];
            $aImgRegex = [];
            $aImgRegex[0] = $aRegexStart[0];
            $aImgRegex[2] = $aRegexStart[1];
            $aImgRegex[4] = $sRegexMiddle;
            $aImgRegex[5] = $sRegexEnd;
            $sImgRegex = \implode('', $aImgRegex);
            if (\false === \preg_match_all($sImgRegex, $css, $aImagesMatches)) {
                throw new Exception\RuntimeException('Error occurred matching for images to include');
            }
            $aImagesMatches[0] = \array_diff($aImagesMatches[0], $aMatches[0]);
            $aImagesMatches[1] = \array_diff($aImagesMatches[1], $aMatches[1]);
            $aImages['include'] = $this->spriteController->CreateSprite($aImagesMatches[1], \true);
            $aImages['exclude'] = $this->spriteController->CreateSprite($aMatches[1], \true);

            return $aImages;
        }
        JCH_DEBUG ? Profiler::stop('ProcessCssUrls', \true) : null;

        return $aMatches;
    }

    /**
     * Generates sprite image and return background positions for image replaced with sprite.
     *
     * @param array $matches Array of css declarations and image url to be included in sprite
     *
     * @throws Exception\RuntimeException
     */
    public function generateSprite(array $matches): array
    {
        JCH_DEBUG ? Profiler::start('GenerateSprite') : null;
        $aDeclaration = $matches[0];
        $aImages = $matches[1];
        $this->spriteController->CreateSprite($aImages);
        $aSpriteCss = $this->spriteController->getCssBackground();
        $aPatterns = [];
        $aPatterns[0] = '#background-position:[^;}]+;?#i';
        // Background position declaration regex
        $aPatterns[1] = '#(background:[^;}]*)\\b((?:top|bottom|left|right|center|-?[0-9]+(?:%|[c-x]{2})?)\\s(?:top|bottom|left|right|center|-?[0-9]+(?:%|[c-x]{2})?))([^;}]*[;}])#i';
        $aPatterns[2] = '#(background-image:)[^;}]+;?#i';
        // Background image declaration regex
        $aPatterns[3] = '#(background:[^;}]*)\\burl\\((?=[^\\)]+\\.(?:png|gif|jpe?g))[^\\)]+\\)([^;}]*[;}])#i';
        // Background image regex
        $sSpriteName = $this->spriteController->getSpriteFileName();
        $aSearch = [];
        $sRelSpritePath = Paths::spritePath(\true).\DIRECTORY_SEPARATOR.$sSpriteName;
        $cdn = $this->container->get(Cdn::class);
        $sRelSpritePath = $cdn->loadCdnResource(Utils::uriFor($sRelSpritePath));
        for ($i = 0; $i < \count($aSpriteCss); ++$i) {
            if (isset($aSpriteCss[$i])) {
                $aSearch['needles'][] = $aDeclaration[$i];
                $aReplacements = [];
                $aReplacements[0] = '';
                $aReplacements[1] = '$1$3';
                $aReplacements[2] = '$1 url('.$sRelSpritePath.'); background-position: '.$aSpriteCss[$i].';';
                $aReplacements[3] = '$1url('.$sRelSpritePath.') '.$aSpriteCss[$i].'$2';
                $sReplacement = \preg_replace($aPatterns, $aReplacements, $aDeclaration[$i]);
                if (\is_null($sReplacement)) {
                    throw new Exception\RuntimeException('Error finding replacements for sprite background positions');
                }
                $aSearch['replacements'][] = $sReplacement;
            }
        }
        JCH_DEBUG ? Profiler::stop('GenerateSprite', \true) : null;

        return $aSearch;
    }
}

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

use function array_unique;
use function defined;
use function implode;
use function preg_match;
use function preg_replace;
use function preg_split;
use function strlen;
use function strtolower;
use function substr;
use function trim;

defined('_JCH_EXEC') or die('Restricted access');

class CombineMediaQueries extends AbstractCallback
{
    /**
     * @var string[]
     */
    private array $cssInfos = [];

    /**
     * @inheritDoc
     */
    public function processMatches(array $matches, string $context): string
    {
        if (empty($this->cssInfos['media'])) {
            return $matches[0];
        }

        if ($context == 'media') {
            return '@media ' . $this->combineMediaQueries(
                $this->cssInfos['media'],
                trim(substr($matches[2], 6))
            ) . '{' . $matches[4] . '}';
        }

        if ($context == 'import') {
            $sMediaQuery = $matches[7];

            $sAtImport = substr($matches[0], 0, -(strlen($sMediaQuery . ';')));

            return $sAtImport . ' ' . $this->combineMediaQueries($this->cssInfos['media'], $sMediaQuery) . ';';
        }

        return '@media ' . $this->cssInfos['media'] . '{' . $matches[0] . '}';
    }

    /**
     *
     * @param string $parentMediaQueryList
     * @param string $childMediaQueryList
     *
     * @return string
     */
    protected function combineMediaQueries(string $parentMediaQueryList, string $childMediaQueryList): string
    {
        $parentMediaQueries = preg_split('#\s++or\s++|,#i', $parentMediaQueryList);
        $childMediaQueries = preg_split('#\s++or\s++|,#i', $childMediaQueryList);

        //$aMediaTypes = array('all', 'aural', 'braille', 'handheld', 'print', 'projection', 'screen', 'tty', 'tv', 'embossed');

        $mediaQueries = [];

        foreach ($parentMediaQueries as $parentMediaQuery) {
            $parentMediaQueryMatches = $this->parseMediaQuery(trim($parentMediaQuery));

            foreach ($childMediaQueries as $childMediaQuery) {
                $mediaQuery = '';

                $childMediaQueryMatches = $this->parseMediaQuery(trim($childMediaQuery));

                if ($parentMediaQueryMatches['keyword'] == 'only' || $childMediaQueryMatches['keyword'] == 'only') {
                    $mediaQuery .= 'only ';
                }

                if ($parentMediaQueryMatches['keyword'] == 'not' && $childMediaQueryMatches['keyword'] == '') {
                    if ($parentMediaQueryMatches['media_type'] == 'all') {
                        $mediaQuery .= '(not ' . $parentMediaQueryMatches['media_type'] . ')';
                    } elseif ($parentMediaQueryMatches['media_type'] == $childMediaQueryMatches['media_type']) {
                        $mediaQuery .= '(not ' . $parentMediaQueryMatches['media_type'] . ') and ' . $childMediaQueryMatches['media_type'];
                    } else {
                        $mediaQuery .= $childMediaQueryMatches['media_type'];
                    }
                } elseif ($parentMediaQueryMatches['keyword'] == '' && $childMediaQueryMatches['keyword'] == 'not') {
                    if ($childMediaQueryMatches['media_type'] == 'all') {
                        $mediaQuery .= '(not ' . $childMediaQueryMatches['media_type'] . ')';
                    } elseif ($parentMediaQueryMatches['media_type'] == $childMediaQueryMatches['media_type']) {
                        $mediaQuery .= $parentMediaQueryMatches['media_type'] . ' and (not ' . $childMediaQueryMatches['media_type'] . ')';
                    } else {
                        $mediaQuery .= $childMediaQueryMatches['media_type'];
                    }
                } elseif ($parentMediaQueryMatches['keyword'] == 'not' && $childMediaQueryMatches['keyword'] == 'not') {
                    $mediaQuery .= 'not ' . $childMediaQueryMatches['keyword'];
                } else {
                    if ($parentMediaQueryMatches['media_type'] == $childMediaQueryMatches['media_type']
                        || $parentMediaQueryMatches['media_type'] == 'all') {
                        $mediaQuery .= $childMediaQueryMatches['media_type'];
                    } elseif ($childMediaQueryMatches['media_type'] == 'all') {
                        $mediaQuery .= $parentMediaQueryMatches['media_type'];
                    } else {
                        //Two different media types are nested and neither is 'all' then
                        //the enclosed rule will not be applied on any media type
                        //We put 'not all' to maintain a syntactically correct combined media type
                        $mediaQuery .= 'not all';

                        //Don't bother including media features in the media query
                        $mediaQueries[] = $mediaQuery;
                        continue;
                    }
                }

                if (isset($parentMediaQueryMatches['expression'])) {
                    $mediaQuery .= ' and ' . $parentMediaQueryMatches['expression'];
                }

                if (isset($childMediaQueryMatches['expression'])) {
                    $mediaQuery .= ' and ' . $childMediaQueryMatches['expression'];
                }

                $mediaQueries[] = $mediaQuery;
            }
        }

        return implode(', ', array_unique($mediaQueries));
    }

    protected function parseMediaQuery(string $sMediaQuery): array
    {
        $aParts = [];

        $sMediaQuery = preg_replace(['#\(\s++#', '#\s++\)#'], ['(', ')'], $sMediaQuery);
        preg_match(
            '#(?:\(?(not|only)\)?)?\s*+(?:\(?(all|screen|print|speech|aural|tv|tty|projection|handheld|braille|embossed)\)?)?(?:\s++and\s++)?(.++)?#si',
            $sMediaQuery,
            $aMatches
        );

        $aParts['keyword'] = isset($aMatches[1]) ? strtolower($aMatches[1]) : '';

        if (isset($aMatches[2]) && $aMatches[2] != '') {
            $aParts['media_type'] = strtolower($aMatches[2]);
        } else {
            $aParts['media_type'] = 'all';
        }

        if (isset($aMatches[3]) && $aMatches[3] != '') {
            $aParts['expression'] = $aMatches[3];
        }

        return $aParts;
    }

    public function setCssInfos($cssInfos): void
    {
        $this->cssInfos = $cssInfos;
    }
}

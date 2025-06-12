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

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class CssSearchObject
{
    protected array $aCssRuleCriteria = [];

    protected array $aCssAtRuleCriteria = [];

    protected string $cssMatch = '';

    protected array $cssMatchCriteria = [];


    public function setCssRuleCriteria(string|array $sCriteria): void
    {
        $this->aCssRuleCriteria[] = $sCriteria;
    }

    public function getCssRuleCriteria(): array
    {
        return $this->aCssRuleCriteria;
    }

    public function setCssAtRuleCriteria(string|array $sCriteria): void
    {
        $this->aCssAtRuleCriteria[] = $sCriteria;
    }

    public function getCssAtRuleCriteria(): array
    {
        return $this->aCssAtRuleCriteria;
    }

    public function setCssMatch(string $cssMatch): void
    {
        $this->cssMatch = $cssMatch;
    }

    public function getCssMatch(): string
    {
        return $this->cssMatch;
    }

    public function setCssMatchCriteria(string $cssMatchCriteria): void
    {
        $this->cssMatchCriteria[] = $cssMatchCriteria;
    }

    public function getCssMatchCriteria(): array
    {
        return $this->cssMatchCriteria;
    }
}

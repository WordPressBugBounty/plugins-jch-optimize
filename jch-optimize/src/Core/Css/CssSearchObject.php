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

    protected array $aCssNestedRuleNames = [];

    protected array $aCssCustomRule = [];

    protected bool $bIsCssCommentSet = false;


    public function setCssRuleCriteria(string $sCriteria): void
    {
        $this->aCssRuleCriteria[] = $sCriteria;
    }

    public function getCssRuleCriteria(): array
    {
        return $this->aCssRuleCriteria;
    }

    public function setCssAtRuleCriteria(string $sCriteria): void
    {
        $this->aCssAtRuleCriteria[] = $sCriteria;
    }

    public function getCssAtRuleCriteria(): array
    {
        return $this->aCssAtRuleCriteria;
    }

    public function setCssNestedRuleName(string $sNestedRule, bool $bRecurse = false, bool $bEmpty = false): void
    {
        $this->aCssNestedRuleNames[] = [
            'name'        => $sNestedRule,
            'recurse'     => $bRecurse,
            'empty-value' => $bEmpty
        ];
    }

    public function getCssNestedRuleNames(): array
    {
        return $this->aCssNestedRuleNames;
    }

    public function setCssCustomRule(string $sCssCustomRule): void
    {
        $this->aCssCustomRule[] = $sCssCustomRule;
    }

    public function getCssCustomRule(): array
    {
        return $this->aCssCustomRule;
    }

    public function setCssComment(): void
    {
        $this->bIsCssCommentSet = true;
    }

    /**
     * @return false|string
     */
    public function getCssComment()
    {
        if ($this->bIsCssCommentSet) {
            return Parser::blockCommentToken();
        }

        return false;
    }
}

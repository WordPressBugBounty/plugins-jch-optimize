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

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class ElementObject
{
    /**
     * @var bool|null   True if element is self-closing, if null, then it's optional
     */
    public ?bool $voidElementOrStartTagOnly = false;

    public bool $isNested = false;
    /**
     * @var array  Name or names of element to search for
     */
    protected array $aNames;
    /**
     * @var array  Array of negative criteria to test against the attributes
     */
    protected array $aNegAttrCriteria = [];
    /**
     * @var array  Array of positive criteria to check against the attributes
     */
    protected array $aPosAttrCriteria = [];
    /**
     * @var array  Array of attributes to capture values
     */
    protected array $posContentCriteria = [];

    protected array $negContentCriteria = [];

    public function __construct()
    {
        $this->aNames[] = Parser::htmlGenericElementNameToken();
    }

    public function setNamesArray(array $aNames): void
    {
        $this->aNames = $aNames;
    }

    public function getNamesArray(): array
    {
        return $this->aNames;
    }

    public function addNegAttrCriteriaRegex(string|array $criteria): void
    {
        $this->aNegAttrCriteria[] = $criteria;
    }

    public function getNegAttrCriteriaArray(): array
    {
        return $this->aNegAttrCriteria;
    }

    public function addPosAttrCriteriaRegex(string $sCriteria): void
    {
        $this->aPosAttrCriteria[] = $sCriteria;
    }

    public function getPosAttrCriteriaArray(): array
    {
        return $this->aPosAttrCriteria;
    }

    public function getPosContentCriteriaRegex(): array
    {
        return $this->posContentCriteria;
    }

    public function addPosContentCriteriaRegex(string $posContentCriteria): void
    {
        $this->posContentCriteria[] = $posContentCriteria;
    }

    public function getNegContentCriteriaRegex(): array
    {
        return $this->negContentCriteria;
    }

    public function addNegContentCriteriaRegex(string $negContentCriteria): void
    {
        $this->negContentCriteria[] = $negContentCriteria;
    }
}

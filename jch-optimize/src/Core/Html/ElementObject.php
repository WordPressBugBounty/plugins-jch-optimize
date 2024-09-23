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
     * @var bool   True if element is self-closing
     */
    public bool $bSelfClosing = false;
    /**
     * @var bool   True to capture inside content of elements
     */
    public bool $bCaptureContent = false;

    public bool $bNegateCriteria = false;
    /**
     * @var array  Name or names of element to search for
     */
    protected array $aNames = ['[a-z0-9]++'];
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
    protected array $aCaptureAttributes = [];
    /**
     * @var string|array Regex criteria for target value
     */
    protected $mValueCriteria = '';

    protected array $aCaptureOneOrBothAttributes = [];

    /**
     * @param $aNames        array    Name(s) of elements to search for
     */
    public function setNamesArray(array $aNames): void
    {
        $this->aNames = $aNames;
    }

    public function getNamesArray(): array
    {
        return $this->aNames;
    }

    public function addNegAttrCriteriaRegex(string $sCriteria): void
    {
        $this->aNegAttrCriteria[] = $sCriteria;
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

    public function setCaptureAttributesArray(array $aAttributes): void
    {
        $this->aCaptureAttributes = $aAttributes;
    }

    public function getCaptureAttributesArray(): array
    {
        return $this->aCaptureAttributes;
    }

    public function setValueCriteriaRegex($mCriteria): void
    {
        $this->mValueCriteria = $mCriteria;
    }

    /**
     * @return array|string
     */
    public function getValueCriteriaRegex()
    {
        return $this->mValueCriteria;
    }

    public function setCaptureOneOrBothAttributesArray(array $aAttributes): void
    {
        $this->aCaptureOneOrBothAttributes = $aAttributes;
    }

    public function getCaptureOneOrBothAttributesArray(): array
    {
        return $this->aCaptureOneOrBothAttributes;
    }
}

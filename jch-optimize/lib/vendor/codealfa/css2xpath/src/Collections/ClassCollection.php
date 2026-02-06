<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace CodeAlfa\Css2Xpath\Collections;

use CodeAlfa\Css2Xpath\Selector\ClassSelector;
use InvalidArgumentException;
use SplObjectStorage;

class ClassCollection extends SplObjectStorage
{
    public function offsetSet(mixed $object, mixed $info = null): void
    {
        if (!$object instanceof ClassSelector) {
            throw new InvalidArgumentException('Only ClassSelector instances can be attached.');
        }
        parent::offsetSet($object, $info);
    }
    public function current(): ClassSelector
    {
        return parent::current();
    }
    /**
     * @deprecated
     */
    public function attach(object $object, mixed $info = null): void
    {
        $this->offsetSet($object, $info);
    }
}

<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core;

class Registry extends \_JchOptimizeVendor\V91\Joomla\Registry\Registry
{
    public function get($path, $default = null): mixed
    {
        if ($default === null) {
            $default = Settings::DEFAULTS[$path] ?? null;
        }

        return parent::get($path, $default);
    }

    public function getBool($setting): bool
    {
        return (bool)$this->get($setting);
    }

    public function getInt($setting): int
    {
        return (int)$this->get($setting);
    }

    public function getArray($setting): array
    {
        return Helper::getArray($this->get($setting));
    }

    public function isEnabled($setting): bool
    {
        return $this->getBool($setting);
    }

    public function isEmpty($setting): bool
    {
        return empty(trim($this->get($setting)));
    }
}

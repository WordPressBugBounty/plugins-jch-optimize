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

namespace JchOptimize\Core\Traits;

use function file_exists;

trait TestableFileExistsTrait
{
    public function fileExists(string $path): bool
    {
        if ($this->params->get('test_running', '0')) {
            return true;
        }

        return @file_exists($path);
    }
}

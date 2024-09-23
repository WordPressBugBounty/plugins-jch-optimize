<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Log;


use JchOptimize\Core\Psr\Log\AbstractLogger;

use function array_merge;
use function array_unique;
use function get_transient;
use function set_transient;

class WordpressNoticeLogger extends AbstractLogger
{
    public function success(string $message, array $context = []): void
    {
        $this->log('success', $message, $context);
    }

    public function log($level, $message, array $context = array()): void
    {
        $messages = [
                ['message' => $message, 'type' => $level]
        ];

        /** @var array $existingMessages */
        if ($existingMessages = get_transient('jch-optimize_notices')) {
            $messages = array_merge($existingMessages, $messages);
        }

        set_transient('jch-optimize_notices', array_unique($messages), 60 * 5);
    }
}
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

namespace JchOptimize\Core\Css\Callbacks\Dependencies;

final class CriticalCssDomainProfiler
{
    /** @var array<string, array{time: float, count: int, max: float}> */
    private array $stats = [];

    private array $marks = [];

    public function addSample(string $domain, float $duration): void
    {
        if (!isset($this->stats[$domain])) {
            $this->stats[$domain] = ['time' => 0.0, 'count' => 0, 'max' => 0.0];
        }

        $this->stats[$domain]['time'] += $duration;
        $this->stats[$domain]['count']++;

        if ($duration > $this->stats[$domain]['max']) {
            $this->stats[$domain]['max'] = $duration;
        }
    }

    /**
     * @return array<string, array{time: float, count: int, max: float}>
     */
    public function snapshot(): array
    {
        return $this->stats;
    }

    public function start(string $domain): void
    {
        $this->marks[$domain] = microtime(true);
    }

    public function stop(string $domain): void
    {
        $this->addSample($domain, microtime(true) - $this->marks[$domain]);
    }
}

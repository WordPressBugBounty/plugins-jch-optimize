<?php

/**
 *  @package   codealfa/regextokenizer
 *  @author    Samuel Marshall <sdmarshall73@gmail.com>
 *  @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace CodeAlfa\RegexTokenizer\Debug;

use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use _JchOptimizeVendor\V91\Psr\Log\NullLogger;

trait Profiler
{
    use LoggerAwareTrait;

    /**
     * Enable/disable profiling.
     */
    protected bool $profilerEnabled = \false;
    /**
     * Threshold in milliseconds for logging a span.
     */
    protected float $profilerThresholdMs = 10.0;
    /**
     * Whether to log the full regex and code.
     */
    protected bool $profilerLogDetails = \true;
    /**
     * Category for logger context.
     */
    protected string $profilerCategory = 'RegexTokenizer';
    /**
     * Previous timestamp (microtime(true)) for "tick" semantics.
     */
    private ?float $profilerPrevStamp = null;
    /**
     * Record a profiling tick. Measures time since previous tick.
     *
     * @param string         $regex
     * @param string         $code
     * @param int|string     $regexNum  Arbitrary identifier (e.g., counter)
     */
    protected function profileRegex(string $regex, string $code, int|string $regexNum = 0): void
    {
        if (!$this->profilerEnabled) {
            return;
        }
        if ($this->logger === null) {
            $this->setLogger(new NullLogger());
        }
        /** @var LoggerInterface $logger */
        $logger = $this->logger;
        $now = microtime(\true);
        if ($this->profilerPrevStamp === null) {
            // First tick – just prime the timer.
            $this->profilerPrevStamp = $now;
            return;
        }
        $elapsedMs = ($now - $this->profilerPrevStamp) * 1000.0;
        $this->profilerPrevStamp = $now;
        if ($elapsedMs < $this->profilerThresholdMs) {
            return;
        }
        $context = ['category' => $this->profilerCategory, 'regex_num' => $regexNum, 'elapsed_ms' => $elapsedMs];
        $logger->debug(sprintf('[RegexTokenizer] #%s took %.3f ms', (string) $regexNum, $elapsedMs), $context);
        if ($this->profilerLogDetails) {
            $logger->debug('regex = ' . $regex, $context);
            $logger->debug('code  = ' . $code, $context);
        }
    }
    /**
     * Reset the internal stopwatch. Optional but sometimes handy.
     */
    protected function resetProfiler(): void
    {
        $this->profilerPrevStamp = null;
    }
}

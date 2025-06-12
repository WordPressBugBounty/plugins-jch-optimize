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

namespace JchOptimize\Core\Css\Callbacks;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use JchOptimize\Core\CacheObject;
use JchOptimize\Core\Css\Components\NestingAtRule;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\Parser;
use JchOptimize\Core\Exception\InvalidArgumentException;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

abstract class AbstractCallback implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected array $conditionalAtRules = ['media', 'supports', 'layer', 'scope', 'container', 'document'];

    private ?Parser $parser = null;

    protected int $recursionLevel = 0;

    protected array $secondaryCssByLevels = [0 => ''];

    protected CacheObject $cacheObject;

    private FileInfo|null $cssInfo = null;

    public function __construct(Container $container, protected Registry $params)
    {
        $this->container = $container;
        $this->cacheObject = new CacheObject();
    }

    public function setCssInfo(FileInfo $cssInfo): static
    {
        $this->cssInfo = $cssInfo;

        return $this;
    }

    public function getCssInfo(): FileInfo
    {
        if ($this->cssInfo instanceof FileInfo) {
            return $this->cssInfo;
        }

        throw new PropertyNotFoundException('CssInfo not set');
    }

    /**
     * @throws PregErrorException
     */
    public function processMatches(array $matches): string
    {
        if (empty($matches[0])) {
            return $matches[0];
        }

        try {
            $nestingAtRule = NestingAtRule::load($matches[0]);

            if (in_array($nestingAtRule->getIdentifier(), $this->conditionalAtRules)) {
                $this->incrementRecursion();
                $processedContent = $this->getParser()->processMatchesWithCallback(
                    $nestingAtRule->getCssRuleList(),
                    $this
                );
                $this->decrementRecursion($nestingAtRule);

                return $nestingAtRule->setCssRuleList($processedContent)->render();
            }
        } catch (InvalidArgumentException) {
        }

        foreach ($this->supportedCssComponents() as $component) {
            try {
                $cssComponent = $component::load($matches[0]);
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($cssComponent instanceof CssComponents) {
                return $this->internalProcessMatches($cssComponent);
            }
        }

        return $matches[0];
    }

    abstract protected function internalProcessMatches(CssComponents $cssComponent): string;

    abstract protected function supportedCssComponents(): array;

    public function setParser(Parser $parser): void
    {
        $this->parser = $parser;
    }

    protected function getParser(): Parser
    {
        if (!$this->parser) {
            throw new PropertyNotFoundException('Parser not set');
        }

        return $this->parser;
    }

    public function getAndResetSecondaryCss(): string
    {
        $css = $this->secondaryCssByLevels[$this->recursionLevel];
        $this->secondaryCssByLevels[$this->recursionLevel] = '';

        return $css;
    }

    public function getCacheObject(): CacheObject
    {
        return $this->cacheObject;
    }

    protected function addToSecondaryCss(CssComponents $cssComponent): void
    {
        $this->secondaryCssByLevels[$this->recursionLevel] .= $cssComponent->render();
    }

    private function incrementRecursion(): void
    {
        $this->recursionLevel++;
        $this->secondaryCssByLevels[$this->recursionLevel] = '';
    }

    private function decrementRecursion(NestingAtRule $nestingAtRule): void
    {
        if ($this->secondaryCssByLevels[$this->recursionLevel] !== '') {
            $this->secondaryCssByLevels[$this->recursionLevel - 1] .= $nestingAtRule
                ->setCssRuleList($this->secondaryCssByLevels[$this->recursionLevel])
                ->render();
        }

        unset($this->secondaryCssByLevels[$this->recursionLevel]);
        $this->recursionLevel--;
    }
}

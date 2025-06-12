<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/joomla-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Css\Components\ImportAtRule;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\HtmlElementInterface;

class FileInfo
{
    protected ?UriInterface $uri = null;

    protected string $content = '';

    protected string $media = '';

    private string $layer = '';

    private string $supports = '';

    private bool $alreadyProcessed = false;

    public function __construct(protected HtmlElementInterface|CssComponents $element)
    {
        $this->applyParts($element);
    }

    private function applyParts(CssComponents|HtmlElementInterface $element): void
    {
        if ($element instanceof Script) {
            if (($src = $element->getSrc()) instanceof UriInterface) {
                $this->uri = $src;
            } else {
                $this->content = $element->getChildren()[0];
            }
        }

        if ($element instanceof Link) {
            if (($href = $element->getHref()) instanceof UriInterface) {
                $this->uri = $href;
            }

            if (!empty($media = $element->getMedia())) {
                $this->media = $media;
            }
        }

        if ($element instanceof Style) {
            $this->content = $element->getChildren()[0];

            if (!empty($media = $element->getMedia())) {
                $this->media = $media;
            }
        }

        if ($element instanceof ImportAtRule) {
            $this->uri = $element->getUri();
            $this->media = $element->getMediaQueriesList();
            $this->layer = $element->getLayer();
            $this->supports = $element->getSupports();
        }
    }

    public function hasUri(): bool
    {
        return $this->uri instanceof UriInterface;
    }

    public function getUri(): UriInterface
    {
        if ($this->uri instanceof UriInterface) {
            return $this->uri;
        }

        throw new PropertyNotFoundException('Uri not set');
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMedia(): string
    {
        return $this->media;
    }

    public function getLayer(): string
    {
        return $this->layer;
    }

    public function getSupports(): string
    {
        return $this->supports;
    }

    public function getElement(): HtmlElementInterface|CssComponents
    {
        return $this->element;
    }

    public function display(): string
    {
        if ($this->hasUri()) {
            return (string)$this->uri;
        } elseif ($this->getType() == 'js') {
            return 'Script Declaration';
        } else {
            return 'Style Declaration';
        }
    }

    public function getType(): string
    {
        return $this->element instanceof Script ? 'js' : 'css';
    }

    public function isAlreadyProcessed(): bool
    {
        return $this->alreadyProcessed;
    }

    public function setAlreadyProcessed(bool $alreadyProcessed): void
    {
        $this->alreadyProcessed = $alreadyProcessed;
    }
}

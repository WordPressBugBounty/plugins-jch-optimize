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

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Laminas\EventManager\Event;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Exception\RuntimeException;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\Utils;

class ImageAttributes
{
    private ?Img $currentImgObj = null;

    private array $imageSize = [];

    private bool $isImageAttrEnabled;

    public function __construct(Registry $params, private Cdn $cdn, private PathsInterface $pathsUtils)
    {
        $this->isImageAttrEnabled = (bool) $params->get('img_attributes_enable', '0');
    }
    public function getImageAttributes(array $images): array
    {
        $imageAttributes = [];

        foreach ($images[0] as $image) {
            if ($this->validateImage($image) === false) {
                $imageAttributes[] = $image;
                continue;
            }

            $imageAttributes[] = $this->addWidthHeightAttributes();

            $this->currentImgObj = null;
            $this->imageSize = [];
        }

        return $imageAttributes;
    }

    private function validateImage(mixed $image): UriInterface|bool
    {
        try {
            $imgObj = HtmlElementBuilder::load($image);
        } catch (PregErrorException) {
            return false;
        }

        if ($imgObj instanceof Img) {
            $this->currentImgObj = $imgObj;
        }

        $uri = $this->getUriFromImageObj($this->currentImgObj);

        if (!$uri instanceof UriInterface) {
            return false;
        }

        $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);

        if (!UriComparator::existsLocally($uri, $this->cdn, $this->pathsUtils)) {
            return false;
        }

        $imagePath = UriConverter::uriToFilePath($uri, $this->pathsUtils, $this->cdn);

        if (!file_exists($imagePath)) {
            return false;
        }

        $imageSize = getimagesize($imagePath);

        if (empty($imageSize) || !is_array($imageSize) || $imageSize[0] <= 1 || $imageSize <= 1) {
            return false;
        }

        $this->imageSize = $imageSize;

        return true;
    }

    private function getUriFromImageObj(Img $imgObj): UriInterface|false
    {
        if ($imgObj->hasAttribute('src')) {
            return $imgObj->getSrc();
        } elseif ($imgObj->hasAttribute('data-src')) {
            return Utils::uriFor($imgObj->attributeValue('data-src'));
        }

        return false;
    }

    private function addWidthHeightAttributes(): string
    {
        if ($this->currentImgObj->hasAttribute('width')) {
            try {
                return $this->addHeightAttributeFromWidth();
            } catch (RuntimeException $e) {
            }
        }

        if ($this->currentImgObj->hasAttribute('height')) {
            try {
                return $this->addWidthAttributeFromHeight();
            } catch (RuntimeException $e) {
            }
        }

        if ($this->isImageAttrEnabled) {
            $this->currentImgObj->width($this->imageSize[0]);
            $this->currentImgObj->height($this->imageSize[1]);
        } else {
            $this->currentImgObj->data('width', $this->imageSize[0]);
            $this->currentImgObj->data('height', $this->imageSize[1]);
        }

        return $this->currentImgObj->render();
    }

    private function addAttrFromExistingAttr(string $existingAttr): string
    {
        $existingAttrValue = $this->currentImgObj->attributeValue($existingAttr);

        if ($existingAttrValue !== false) {
            $existingAttrValue = $this->cleanAttrValue($existingAttrValue, $existingAttr);

            if ($existingAttrValue) {
                return $this->addOtherAttrByAspectRatio($existingAttrValue, $existingAttr);
            }
        }

        throw new RuntimeException('Attribute not found');
    }

    private function cleanAttrValue(mixed $value, $existingAttr): string
    {
        $cleanedAttrValue = (string) preg_replace('#[^0-9]#', '', $value, -1, $count);

        if ($count > 0) {
            $this->currentImgObj->attribute($existingAttr, $cleanedAttrValue);
        }

        return $cleanedAttrValue;
    }

    private function addHeightAttributeFromWidth(): string
    {
        return $this->addAttrFromExistingAttr('width');
    }

    private function addWidthAttributeFromHeight(): string
    {
        return $this->addAttrFromExistingAttr('height');
    }

    private function addOtherAttrByAspectRatio(string $existingAttrValue, string $existingAttr): string
    {
        if ($existingAttr == 'width') {
            $height = (string) round(
                ($this->imageSize[1] / $this->imageSize[0]) * (int) $existingAttrValue
            );
            $this->isImageAttrEnabled
                ? $this->currentImgObj->height($height) : $this->currentImgObj->data('height', $height);
        } else {
            $width = (string) round(
                ($this->imageSize[0] / $this->imageSize[1]) * (int) $existingAttrValue
            );
            $this->isImageAttrEnabled
                ? $this->currentImgObj->width($width) : $this->currentImgObj->data('width', $width);
        }

        return $this->currentImgObj->render();
    }

    public function loadImageAttributesCss(Event $event): void
    {
        if ($this->isImageAttrEnabled) {
            $style = HtmlElementBuilder::style()
                ->class('jchoptimize-image-attributes')
                ->addChild('img{max-width: 100%; height: auto;}');

            /** @var HtmlManager $htmlManager */
            $htmlManager = $event->getTarget();
            $htmlManager->appendChildToHead($style->render());
        }
    }
}

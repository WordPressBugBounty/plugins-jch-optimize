<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Sprite\Handler;

\defined('_JCH_EXEC') or exit('Restricted access');
class Imagick extends \JchOptimize\Core\Css\Sprite\Handler\AbstractHandler
{
    public function getSupportedFormats(): array
    {
        $imageTypes = [];

        try {
            $oImagick = new \Imagick();
            $imageFormats = $oImagick->queryFormats();
        } catch (\ImagickException $e) {
            $this->logger->error($e->getMessage());

            return [];
        }
        // store supported formats for populating drop downs etc later
        if (\in_array('PNG', $imageFormats)) {
            $imageTypes[] = 'PNG';
            $this->spriteFormats[] = 'PNG';
        }
        if (\in_array('GIF', $imageFormats)) {
            $imageTypes[] = 'GIF';
            $this->spriteFormats[] = 'GIF';
        }
        if (\in_array('JPG', $imageFormats) || \in_array('JPEG', $imageFormats)) {
            $imageTypes[] = 'JPG';
        }

        return $imageTypes;
    }

    public function createSprite($spriteWidth, $spriteHeight, $bgColour, $outputFormat): \Imagick
    {
        $spriteObject = new \Imagick();
        // create a new image - set background according to transparency
        if (!empty($this->options['background'])) {
            $spriteObject->newImage($spriteWidth, $spriteHeight, new \ImagickPixel("#{$bgColour}"), $outputFormat);
        } else {
            if ($this->options['is-transparent']) {
                $spriteObject->newImage($spriteWidth, $spriteHeight, new \ImagickPixel('#000000'), $outputFormat);
            } else {
                $spriteObject->newImage($spriteWidth, $spriteHeight, new \ImagickPixel('#ffffff'), $outputFormat);
            }
        }
        // check for transparency option
        if ($this->options['is-transparent']) {
            // set background colour to transparent
            // if no background colour use black
            if (!empty($this->options['background'])) {
                $spriteObject->transparentPaintImage(new \ImagickPixel("#{$bgColour}"), 0.0, 0, \false);
            } else {
                $spriteObject->transparentPaintImage(new \ImagickPixel('#000000'), 0.0, 0, \false);
            }
        }

        return $spriteObject;
    }

    public function createBlankImage($fileInfos): \Imagick
    {
        $currentImage = new \Imagick();
        $currentImage->newImage($fileInfos['original-width'], $fileInfos['original-height'], new \ImagickPixel('#ffffff'));

        return $currentImage;
    }

    /**
     * @param \Imagick $currentImage
     *
     * @throws \ImagickException
     *
     * @since version
     */
    public function resizeImage($spriteObject, $currentImage, $fileInfos)
    {
        $currentImage->thumbnailImage($fileInfos['width'], $fileInfos['height']);
    }

    /**
     * @param \Imagick $spriteObject
     * @param \Imagick $currentImage
     *
     * @throws \ImagickException
     */
    public function copyImageToSprite($spriteObject, $currentImage, $fileInfos, $resize)
    {
        $spriteObject->compositeImage($currentImage, $currentImage->getImageCompose(), $fileInfos['x'], $fileInfos['y']);
    }

    /**
     * @param \Imagick $imageObject
     *
     * @since version
     */
    public function destroy($imageObject)
    {
        $imageObject->destroy();
    }

    public function createImage($fileInfos): \Imagick
    {
        // Imagick auto-detects file extension when creating object from image
        $oImage = new \Imagick();
        $oImage->readImage($fileInfos['path']);

        return $oImage;
    }

    /**
     * @param \Imagick $imageObject
     * @param string   $extension
     * @param string   $fileName
     *
     * @throws \ImagickException
     */
    public function writeImage($imageObject, $extension, $fileName)
    {
        // check if we want to resample image to lower number of colours (to reduce file size)
        if (\in_array($extension, ['gif', 'png']) && 'true-colour' != $this->options['image-num-colours']) {
            $imageObject->quantizeImage($this->options['image-num-colours'], \Imagick::COLORSPACE_RGB, 0, \false, \false);
        }
        // if we're creating a JEPG set image quality - 0% - 100%
        if (\in_array($extension, ['jpg', 'jpeg'])) {
            $imageObject->setCompression(\Imagick::COMPRESSION_JPEG);
            $imageObject->SetCompressionQuality($this->options['image-quality']);
        }
        // write out image to file
        $imageObject->writeImage($fileName);
    }
}

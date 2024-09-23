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

namespace JchOptimize\Core\Css\Sprite\Handler;

use ImagickException;
use ImagickPixel;

use function defined;
use function in_array;

defined('_JCH_EXEC') or die('Restricted access');

class Imagick extends AbstractHandler
{
    public function getSupportedFormats(): array
    {
        $imageTypes = [];

        try {
            $oImagick = new \Imagick();
            $aImageFormats = $oImagick->queryFormats();
        } catch (ImagickException $e) {
            $this->logger->error($e->getMessage());
        }

        // store supported formats for populating drop downs etc later
        if (in_array('PNG', $aImageFormats)) {
            $imageTypes[] = 'PNG';

            $this->spriteFormats[] = 'PNG';
        }
        if (in_array('GIF', $aImageFormats)) {
            $imageTypes[] = 'GIF';

            $this->spriteFormats[] = 'GIF';
        }
        if (in_array('JPG', $aImageFormats) || in_array('JPEG', $aImageFormats)) {
            $imageTypes[] = 'JPG';
        }

        return $imageTypes;
    }

    public function createSprite($spriteWidth, $spriteHeight, $bgColour, $outputFormat): \Imagick
    {
        $spriteObject = new \Imagick();
        // create a new image - set background according to transparency
        if (!empty($this->options['background'])) {
            $spriteObject->newImage($spriteWidth, $spriteHeight, new ImagickPixel("#$bgColour"), $outputFormat);
        } else {
            if ($this->options['is-transparent']) {
                $spriteObject->newImage($spriteWidth, $spriteHeight, new ImagickPixel('#000000'), $outputFormat);
            } else {
                $spriteObject->newImage($spriteWidth, $spriteHeight, new ImagickPixel('#ffffff'), $outputFormat);
            }
        }

        // check for transparency option
        if ($this->options['is-transparent']) {
            // set background colour to transparent
            // if no background colour use black
            if (!empty($this->options['background'])) {
                $spriteObject->transparentPaintImage(new ImagickPixel("#$bgColour"), 0.0, 0, false);
            } else {
                $spriteObject->transparentPaintImage(new ImagickPixel("#000000"), 0.0, 0, false);
            }
        }

        return $spriteObject;
    }

    public function createBlankImage($fileInfos): \Imagick
    {
        $currentImage = new \Imagick();

        $currentImage->newImage(
            $fileInfos['original-width'],
            $fileInfos['original-height'],
            new ImagickPixel('#ffffff')
        );

        return $currentImage;
    }

    /**
     * @param $spriteObject
     * @param \Imagick $currentImage
     * @param $fileInfos
     *
     * @throws ImagickException
     *
     * @since version
     *
     * @return void
     */
    public function resizeImage($spriteObject, $currentImage, $fileInfos)
    {
        $currentImage->thumbnailImage($fileInfos['width'], $fileInfos['height']);
    }

    /**
     * @param \Imagick $spriteObject
     * @param \Imagick $currentImage
     * @param $fileInfos
     * @param $resize
     *
     * @throws ImagickException
     *
     * @return void
     */
    public function copyImageToSprite($spriteObject, $currentImage, $fileInfos, $resize)
    {
        $spriteObject->compositeImage(
            $currentImage,
            $currentImage->getImageCompose(),
            $fileInfos['x'],
            $fileInfos['y']
        );
    }

    /**
     * @param \Imagick $imageObject
     *
     * @since version
     *
     * @return void
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
     * @param string $extension
     * @param string $fileName
     *
     * @throws ImagickException
     *
     * @return void
     */
    public function writeImage($imageObject, $extension, $fileName)
    {
        // check if we want to resample image to lower number of colours (to reduce file size)
        if (in_array($extension, array(
                'gif',
                'png'
            )) && $this->options['image-num-colours'] != 'true-colour') {
            $imageObject->quantizeImage($this->options['image-num-colours'], \Imagick::COLORSPACE_RGB, 0, false, false);
        }
        // if we're creating a JEPG set image quality - 0% - 100%
        if (in_array($extension, array('jpg', 'jpeg'))) {
            $imageObject->setCompression(\Imagick::COMPRESSION_JPEG);
            $imageObject->SetCompressionQuality($this->options['image-quality']);
        }
        // write out image to file
        $imageObject->writeImage($fileName);
    }
}

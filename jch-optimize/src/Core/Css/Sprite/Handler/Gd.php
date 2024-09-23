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

use function defined;
use function gd_info;
use function hexdec;
use function imagealphablending;
use function imagecolorallocate;
use function imagecolorallocatealpha;
use function imagecolortransparent;
use function imagecopy;
use function imagecopyresampled;
use function imagecreate;
use function imagecreatefromgif;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagegif;
use function imagejpeg;
use function imagepng;
use function imagesavealpha;
use function imagetruecolortopalette;

defined('_JCH_EXEC') or die('Restricted access');

class Gd extends AbstractHandler
{
    public function getSupportedFormats(): array
    {
        // get info about installed GD library to get image types (some versions of GD don't include GIF support)
        $oGD        = gd_info();
        $imageTypes = [];
        // store supported formats for populating drop downs etc later
        if (isset($oGD['PNG Support'])) {
            $imageTypes[] = 'PNG';

            $this->spriteFormats[] = 'PNG';
        }

        if (isset($oGD['GIF Create Support'])) {
            $imageTypes[] = 'GIF';
        }

        if (isset($oGD['JPG Support']) || isset($oGD['JPEG Support'])) {
            $imageTypes[] = 'JPG';
        }

        return $imageTypes;
    }

    /**
     * @return false|resource
     */
    public function createSprite($spriteWidth, $spriteHeight, $bgColour, $outputFormat)
    {
        if ($this->options['is-transparent'] && ! empty($this->options['background'])) {
            $oSprite = imagecreate($spriteWidth, $spriteHeight);
        } else {
            $oSprite = imagecreatetruecolor($spriteWidth, $spriteHeight);
        }

        // check for transparency option
        if ($this->options['is-transparent']) {
            if ($outputFormat == "png") {
                imagealphablending($oSprite, false);
                $colorTransparent = imagecolorallocatealpha($oSprite, 0, 0, 0, 127);
                imagefill($oSprite, 0, 0, $colorTransparent);
                imagesavealpha($oSprite, true);
            } elseif ($outputFormat == "gif") {
                $iBgColour = imagecolorallocate($oSprite, 0, 0, 0);

                imagecolortransparent($oSprite, $iBgColour);
            }
        } else {
            if (empty($bgColour)) {
                $bgColour = 'ffffff';
            }
            $iBgColour = hexdec($bgColour);
            $iBgColour = imagecolorallocate(
                    $oSprite,
                    0xFF & ($iBgColour >> 0x10),
                    0xFF & ($iBgColour >> 0x8),
                    0xFF & $iBgColour
            );
            imagefill($oSprite, 0, 0, $iBgColour);
        }

        return $oSprite;
    }

    /**
     * @return false|resource
     */
    public function createBlankImage($fileInfos)
    {
        $oCurrentImage = imagecreatetruecolor($fileInfos['original-width'], $fileInfos['original-height']);
        imagecolorallocate($oCurrentImage, 255, 255, 255);

        return $oCurrentImage;
    }

    /**
     * @return void
     */
    public function resizeImage($spriteObject, $currentImage, $fileInfos)
    {
        imagecopyresampled(
                $spriteObject,
                $currentImage,
                $fileInfos['x'],
                $fileInfos['y'],
                0,
                0,
                $fileInfos['width'],
                $fileInfos['height'],
                $fileInfos['original-width'],
                $fileInfos['original-height']
        );
    }

    /**
     * @return void
     */
    public function copyImageToSprite($spriteObject, $currentImage, $fileInfos, $resize)
    {
        // if already resized the image will have been copied as part of the resize
        if ( ! $resize) {
            imagecopy(
                    $spriteObject,
                    $currentImage,
                    $fileInfos['x'],
                    $fileInfos['y'],
                    0,
                    0,
                    $fileInfos['width'],
                    $fileInfos['height']
            );
        }
    }

    /**
     * @return void
     */
    public function destroy($imageObject)
    {
        imagedestroy($imageObject);
    }

    /**
     * @return false|resource
     */
    public function createImage($fileInfos)
    {
        $sFile = $fileInfos['path'];

        switch ($fileInfos['ext']) {
            case 'jpg':
            case 'jpeg':
                $oImage = @imagecreatefromjpeg($sFile);
                break;
            case 'gif':
                $oImage = @imagecreatefromgif($sFile);
                break;
            case 'png':
                $oImage = @imagecreatefrompng($sFile);
                break;
            default:
                $oImage = @imagecreatefromstring($sFile);
        }

        return $oImage;
    }

    /**
     * @return void
     */
    public function writeImage($imageObject, $extension, $fileName)
    {
        // check if we want to resample image to lower number of colours (to reduce file size)
        if (in_array($extension, array('gif', 'png')) && $this->options['image-num-colours'] != 'true-colour') {
            imagetruecolortopalette($imageObject, true, $this->options['image-num-colours']);
        }

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                // GD takes quality setting in main creation function
                imagejpeg($imageObject, $fileName, $this->options['image-quality']);
                break;
            case 'gif':
                // force colour palette to 256 colours if saving sprite image as GIF
                // this will happen anyway (as GIFs can't be more than 256 colours)
                // but the quality will be better if pre-forcing
                if (
                        $this->options['is-transparent'] && (
                                $this->options['image-num-colours'] == -1 || $this->options['image-num-colours'] > 256 || $this->options['image-num-colours'] == 'true-colour')
                ) {
                    imagetruecolortopalette($imageObject, true, 256);
                }
                imagegif($imageObject, $fileName);
                break;
            case 'png':
                imagepng($imageObject, $fileName, 0);
                break;
        }
    }
}

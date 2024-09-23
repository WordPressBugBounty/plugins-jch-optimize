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

namespace JchOptimize\Core\Css\Sprite;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface HandlerInterface
{

    public function getSupportedFormats();

    public function createSprite($spriteWidth, $spriteHeight, $bgColour, $outputFormat);

    public function createBlankImage($fileInfos);

    public function resizeImage($spriteObject, $currentImage, $fileInfos);

    public function copyImageToSprite($spriteObject, $currentImage, $fileInfos, $resize);

    public function destroy($imageObject);

    public function createImage($fileInfos);

    public function writeImage($imageObject, $extension, $fileName);
}

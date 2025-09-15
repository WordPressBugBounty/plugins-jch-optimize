<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Preloads;

use JchOptimize\Core\Uri\Utils;
use SplObjectStorage;

/**
 * @template-extends SplObjectStorage<Preload, null>
 */
class PreloadsCollection extends SplObjectStorage
{
    public function getHash(object $object): string
    {
        return md5($object->getHref());
    }

    /**
     * @param object&Preload $object
     * @param mixed $info
     * @return void
     * @psalm-suppress ParamNameMismatch
     */
    public function attach(object $object, mixed $info = null): void
    {
        $this->rewind();

        while ($this->valid()) {
            $preload = $this->current();

            if ($preload->getAs() == 'font') {
                $existingHref = $preload->getHref();
                $newHref = $object->getHref();
                $existingFilename = Utils::filename($existingHref);
                $existingExt = Utils::fileExtension($existingHref);
                $newFilename = Utils::filename($newHref);
                $newExt = Utils::fileExtension($newHref);

                if (
                    $existingHref->getAuthority() == $newHref->getAuthority()
                    && $existingFilename == $newFilename
                ) {
                    if ($newExt == 'woff2') {
                        $this->detach($preload);
                        break;
                    }

                    if (
                        ($newExt == 'woff' || $newExt == 'ttf')
                        && $existingExt == 'woff2'
                    ) {
                        return;
                    }

                    if ($newExt == 'woff' && $existingExt == 'ttf') {
                        $this->detach($preload);
                        break;
                    }

                    if ($newExt == 'ttf') {
                        return;

                    }
                }
            }

            $this->next();
        }

        parent::attach($object, $info);
    }
}

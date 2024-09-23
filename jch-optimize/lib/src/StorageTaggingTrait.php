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

namespace JchOptimize\Core;

use _JchOptimizeVendor\Laminas\Cache\Exception\ExceptionInterface;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use JchOptimize\Core\PageCache\PageCache;

use function md5;

\defined('_JCH_EXEC') or exit('Restricted access');
trait StorageTaggingTrait
{
    /**
     * @param mixed $id
     *
     * @throws ExceptionInterface
     */
    protected function tagStorage($id, ?UriInterface $currentUrl = null): void
    {
        // If item not already set for tagging, set it
        $this->taggableCache->addItem($id, 'tag');
        $pageCache = $this->getContainer()->get(PageCache::class);
        if (null === $currentUrl) {
            $currentUrl = $pageCache->getCurrentPage();
        }
        // Always attempt to store tags, item could be set on another page
        $this->setStorageTags($id, $currentUrl);
        // create an id for currentUrl and tag the cache ids saved on that page
        // $tagPageId = md5($currentUrl);
        // Record ids of all files used on this page
        // $this->taggableCache->addItem($tagPageId, (string)$currentUrl);
        // $this->setStorageTags($tagPageId, $id);
    }

    private function setStorageTags(string $id, string $tag): void
    {
        $tags = $this->taggableCache->getTags($id);
        // If current tag not yet included, add it.
        if (\is_array($tags) && !\in_array($tag, $tags)) {
            $this->taggableCache->setTags($id, \array_merge($tags, [$tag]));
        } elseif (empty($tags)) {
            $this->taggableCache->setTags($id, [$tag]);
        }
    }
}

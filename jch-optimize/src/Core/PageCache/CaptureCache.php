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

namespace JchOptimize\Core\PageCache;

use Exception;
use JchOptimize\Core\Admin\Tasks;
use JchOptimize\Core\Exception\InvalidArgumentException;
use JchOptimize\Core\Helper;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Utility;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Pattern\CaptureCache as LaminasCaptureCache;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Psr\Http\Message\UriInterface;

use function defined;
use function file_exists;
use function file_get_contents;
use function gzencode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function strpos;
use function strtr;

defined('_JCH_EXEC') or die('Restricted access');

class CaptureCache extends PageCache
{
    /**
     * @var bool
     */
    protected bool $captureCacheEnabled = true;
    /**
     * @var LaminasCaptureCache
     */
    private LaminasCaptureCache $captureCache;
    /**
     * @var string
     */
    private string $captureCacheId;
    /**
     * @var string
     */
    private string $startHtaccessLine = '## BEGIN CAPTURE CACHE - JCH OPTIMIZE ##';
    /**
     * @var string
     */
    private string $endHtaccessLine = '## END CAPTURE CACHE - JCH OPTIMIZE ##';

    /**
     * @param Registry $params
     * @param Input $input
     * @param StorageInterface $pageCacheStorage
     * @param StorageInterface&TaggableInterface&IterableInterface $taggableCache
     * @param LaminasCaptureCache $captureCache
     */
    public function __construct(
        Registry            $params,
        Input               $input,
        StorageInterface    $pageCacheStorage,
        $taggableCache,
        LaminasCaptureCache $captureCache
    ) {
        parent::__construct($params, $input, $pageCacheStorage, $taggableCache);

        $this->captureCache = $captureCache;

        if ($this->params->get('pro_cache_platform', '0')) {
            $this->captureCacheEnabled = false;
        }

        $uri = $this->getCurrentPage();

        //Don't use capture cache when there's query
        if (!Utility::isAdmin() && $uri->getQuery() !== '') {
            $this->captureCacheEnabled = false;
        }

        //Don't use capture cache when URL ends in index.php to avoid conflicts with CMS redirects
        if (!Utility::isAdmin()
            && trim($uri->getPath(), '/') == trim(SystemUri::basePath() . 'index.php', '/')
            && empty($uri->getQuery())) {
            $this->captureCacheEnabled = false;
        }
    }

    public function getItems(): array
    {
        $items = parent::getItems();
        $filteredItems = [];
        //set http-request tag if a cache file exists for this item
        foreach ($items as $item) {
            $uri = Utils::uriFor($item['url']);

            $captureCacheId = $this->getCaptureCacheIdFromPage($uri);
            $item['http-request'] = $this->captureCache->has($captureCacheId) ? 'yes' : 'no';

            //filter by HTTP Requests
            if (!empty($this->filters['filter_http-request'])) {
                if ($item['http-request'] != $this->filters['filter_http-request']) {
                    continue;
                }
            }

            $filteredItems[] = $item;
        }

        //If we're sorting by http-request we'll need to re-sort
        if (strpos($this->lists['list_fullordering'], 'http-request') === 0) {
            $this->sortItems($filteredItems, $this->lists['list_fullordering']);
        }

        return $filteredItems;
    }

    private function getCaptureCacheIdFromPage(?UriInterface $page = null): string
    {
        $uri = ((string)$page === '' || is_null($page)) ? $this->getCurrentPage() : $page;
        $id = $uri->getScheme() . '/' . $uri->getHost() . '/' . $uri->getPath() . '/' . $uri->getQuery();
        $id .= '/index.html';

        return Path::clean($id);
    }

    /**
     * @return void
     * @throws ExceptionInterface
     */
    public function initialize(): void
    {
        $this->captureCacheId = $this->getCaptureCacheIdFromPage();

        //If user is logged in we'll need to set a cookie, so they won't see pages cached by another user
        if (!Utility::isGuest() && !$this->input->cookie->get(
            'jch_optimize_no_cache_user_state'
        ) == 'user_logged_in') {
            $options = [
                'httponly' => true,
                'samesite' => 'Lax'
            ];

            $this->input->cookie->set('jch_optimize_no_cache_user_state', 'user_logged_in', $options);
        } //if they're logged out we can delete the cookie, so they can now see cached pages
        elseif (Utility::isGuest() && $this->input->cookie->get(
            'jch_optimize_no_cache_user_state'
        ) == 'user_logged_in') {
            $options = [
                'expires' => 1,
                'httponly' => true,
                'samesite' => 'Lax'
            ];

            $this->input->cookie->set('jch_optimize_no_cache_user_state', '', $options);
        }

        parent::initialize();
    }

    public function store(string $html): string
    {
        //Tag should be set in parent::store()
        $html = parent::store($html);

        //This function will check for a valid tag before saving capture cache
        $this->setCaptureCache($html);

        return $html;
    }

    protected function setCaptureCache(string $html)
    {
        if ($this->getCachingEnabled()
            && $this->isCaptureCacheEnabled()
            && !empty($this->taggableCache->getTags($this->cacheId))
            && !$this->captureCache->has($this->captureCacheId)
        ) {

            try {
                $html = $this->tagCaptureCacheHtml($html);
                $this->captureCache->set($html, $this->captureCacheId);

                //Gzip
                $html = preg_replace('#and served using HTTP Request#', '\0 (Gzipped)', $html);
                $htmlGz = gzencode($html, 9);
                $this->captureCache->set($htmlGz, $this->getGzippedCaptureCacheId($this->captureCacheId));
            } catch (Exception $e) {
            }
        }
    }

    private function tagCaptureCacheHtml(string $content): ?string
    {
        return preg_replace('#Cached by JCH Optimize on .*? GMT#', '\0 and served using HTTP Request', $content);
    }

    private function getGzippedCaptureCacheId(string $id): string
    {
        return $id . '.gz';
    }

    public function deleteItemById(string $id): bool
    {
        $result = 1;

        try {
            $captureCacheId = $this->getCaptureCacheIdFromPageCacheId($id);
            $gzCaptureCacheId = $this->getGzippedCaptureCacheId($captureCacheId);

            $this->captureCache->remove($captureCacheId);
            $this->captureCache->remove($gzCaptureCacheId);

            $result &= (int)!$this->captureCache->has($captureCacheId);
            $result &= (int)!$this->captureCache->has($gzCaptureCacheId);
        } catch (Exception $e) {
            $result = false;
        }

        if ($result) {
            //Delete parent cache only if successful because tag will be deleted here
            $result &= (int)parent::deleteItemById($id);
        }

        return (bool)$result;
    }

    public function getCaptureCacheIdFromPageCacheId(string $id): string
    {
        $tags = $this->taggableCache->getTags($id);

        if (!empty($tags[1])) {
            return $this->getCaptureCacheIdFromPage(Utils::uriFor($tags[1]));
        }

        throw new InvalidArgumentException('No tags found for cache id');
    }

    public function deleteItemsByIds(array $ids): bool
    {
        $result = 1;

        foreach ($ids as $id) {
            $result &= (int)$this->deleteItemById($id);
        }

        return (bool)$result;
    }

    public function deleteAllItems(): bool
    {
        $result = 1;
        $result &= (int)$this->deleteCaptureCacheDir();

        //Only delete parent if successful, tags will be deleted here
        if ($result) {
            $result &= (int)parent::deleteAllItems();
        }

        return (bool)$result;
    }

    private function deleteCaptureCacheDir(): bool
    {
        try {
            if (file_exists(Paths::captureCacheDir())) {
                return Folder::delete(Paths::captureCacheDir());
            }
        } catch (Exception $e) {
            //Let's try another way
            try {
                if (!Helper::deleteFolder(Paths::captureCacheDir())) {
                    $this->logger->error('Error trying to delete Capture Cache dir: ' . $e->getMessage());
                }
            } catch (Exception $e) {
            }
        }

        return !file_exists(Paths::captureCacheDir());
    }

    /**
     * @return void
     */
    public function updateHtaccess(): void
    {
        $pluginState = Cache::isPageCacheEnabled($this->params, true);

        //If Capture Cache not enabled just clean htaccess and leave
        if (!$pluginState || !$this->params->get('pro_capture_cache_enable', '1')
            || $this->params->get('pro_cache_platform', '0')
            || !$this->captureCacheEnabled) {
            $this->cleanHtaccess();

            return;
        }

        $captureCacheDir = strtr(Paths::captureCacheDir(), '\\', '/');
        $relCaptureCacheDir = strtr(Paths::captureCacheDir(true), '\\', '/');
        $jchVersion = JCH_VERSION;

        $htaccessContents = <<<APACHECONFIG

$this->startHtaccessLine
<IfModule mod_headers.c>
	Header set X-Cached-By: "JCH Optimize v$jchVersion"
</IfModule>

<IfModule mod_rewrite.c>
	RewriteEngine On
	
	RewriteRule "\.html\.gz$" "-" [T=text/html,E=no-gzip:1,E=no-brotli:1,L]
	
	<IfModule mod_headers.c>
		<FilesMatch "\.html\.gz$" >
			Header set Content-Encoding gzip
			Header set Vary Accept-Encoding
		</FilesMatch>
		
		RewriteRule .* - [E=JCH_GZIP_ENABLED:yes]
	</IfModule>
	
	<IfModule !mod_headers.c>
		<IfModule mod_mime.c>
		 	AddEncoding gzip .gz
		</IfModule>
		
		RewriteRule .* - [E=JCH_GZIP_ENABLED:yes]
	</IfModule>
	
	RewriteCond %{ENV:JCH_GZIP_ENABLED} ^yes$
	RewriteCond %{HTTP:Accept-Encoding} gzip
	RewriteRule .* - [E=JCH_GZIP:.gz]
	
	RewriteRule .* - [E=JCH_SCHEME:http]
	
	RewriteCond %{HTTPS} on [OR]
	RewriteCond %{SERVER_PORT} ^443$
	RewriteRule .* - [E=JCH_SCHEME:https]
    
	RewriteCond %{REQUEST_METHOD} ^GET 
	RewriteCond %{HTTP_COOKIE} !jch_optimize_no_cache
	RewriteCond "{$captureCacheDir}/%{ENV:JCH_SCHEME}/%{HTTP_HOST}%{REQUEST_URI}/%{QUERY_STRING}/index\.html%{ENV:JCH_GZIP}" -f
	RewriteRule .* "{$relCaptureCacheDir}/%{ENV:JCH_SCHEME}/%{HTTP_HOST}%{REQUEST_URI}/%{QUERY_STRING}/index.html%{ENV:JCH_GZIP}" [L]
</IfModule>
$this->endHtaccessLine

APACHECONFIG;

        $contents = $this->cleanHtaccess(true);
        $endHtaccessLineRegex = preg_quote(rtrim(Tasks::$endHtaccessLine, "# \n\r\t\v\x00"), '#') . '[^\r\n]*[\r\n]*';

        if (preg_match('#' . $endHtaccessLineRegex . '#', $contents)) {
            $updatedContents = preg_replace(
                '#' . $endHtaccessLineRegex . '#',
                '\0' . PHP_EOL . $htaccessContents . PHP_EOL,
                $contents
            );
        } else {
            $updatedContents = $htaccessContents . PHP_EOL . $contents;
        }

        File::write($this->getHtaccessFile(), $updatedContents);
    }

    public function cleanHtaccess(bool $returnContents = false): ?string
    {
        $htaccess = $this->getHtaccessFile();

        if (file_exists($htaccess)) {
            $contents = file_get_contents($htaccess);

            $endHtaccessLineRegex = preg_quote(rtrim($this->endHtaccessLine, "# \n\r\t\v\x00")) . '[^\r\n]*[\r\n]*';

            $htaccessRegex = '@[\r\n]*' . $this->startHtaccessLine . '.*?' . $endHtaccessLineRegex . '@s';
            $cleanContents = preg_replace($htaccessRegex, PHP_EOL, $contents, -1, $count);

            if ($returnContents) {
                return $cleanContents;
            }

            if ($count > 0) {
                File::write($htaccess, $cleanContents);
            }
        }

        $this->deleteCaptureCacheDir();

        return null;
    }

    public function hasCaptureCache(UriInterface $uri): bool
    {
        $captureCacheId = $this->getCaptureCacheIdFromPage($uri);

        return $this->captureCache->has($captureCacheId);
    }

    private function getHtaccessFile(): string
    {
        return Paths::rootPath() . '/.htaccess';
    }
}

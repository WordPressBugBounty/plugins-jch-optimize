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

namespace JchOptimize\Core\Admin;

use JchOptimize\ContainerFactory;
use JchOptimize\Core\Admin\Ajax\OptimizeImage;
use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Plugin;
use Joomla\Filesystem\Exception\FilesystemException;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function clearstatcache;
use function defined;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_null;
use function preg_quote;
use function preg_replace;
use function print_r;
use function rand;
use function rtrim;

defined('_JCH_EXEC') or die('Restricted access');

class Tasks
{
    public static string $startHtaccessLine = '## BEGIN EXPIRES CACHING - JCH OPTIMIZE ##';

    public static string $endHtaccessLine = '## END EXPIRES CACHING - JCH OPTIMIZE ##';

    /**
     * @param bool|null $success
     * @return ?string
     */
    public static function leverageBrowserCaching(?bool &$success = null): ?string
    {
        $htaccess = Paths::rootPath() . '/.htaccess';

        if (file_exists($htaccess)) {
            $contents      = file_get_contents($htaccess);
            $cleanContents = preg_replace(self::getHtaccessRegex(), PHP_EOL, $contents);

            $startLine = self::$startHtaccessLine;
            $endLine   = self::$endHtaccessLine;

            $expires = <<<APACHECONFIG

$startLine
<IfModule mod_expires.c>
	ExpiresActive on

	# Your document html
	ExpiresByType text/html "access plus 0 seconds"

	# Data
	ExpiresByType text/xml "access plus 0 seconds"
	ExpiresByType application/xml "access plus 0 seconds"
	ExpiresByType application/json "access plus 0 seconds"

	# Feed
	ExpiresByType application/rss+xml "access plus 1 hour"
	ExpiresByType application/atom+xml "access plus 1 hour"

	# Favicon (cannot be renamed)
	ExpiresByType image/x-icon "access plus 1 week"

	# Media: images, video, audio
	ExpiresByType image/gif "access plus 1 year"
	ExpiresByType image/png "access plus 1 year"
	ExpiresByType image/jpg "access plus 1 year"
	ExpiresByType image/jpeg "access plus 1 year"
	ExpiresByType image/webp "access plus 1 year"
	ExpiresByType audio/ogg "access plus 1 year"
	ExpiresByType video/ogg "access plus 1 year"
	ExpiresByType video/mp4 "access plus 1 year"
	ExpiresByType video/webm "access plus 1 year"

	# HTC files (css3pie)
	ExpiresByType text/x-component "access plus 1 year"

	# Webfonts
	ExpiresByType image/svg+xml "access plus 1 year"
	ExpiresByType font/* "access plus 1 year"
	ExpiresByType application/x-font-ttf "access plus 1 year"
	ExpiresByType application/x-font-truetype "access plus 1 year"
	ExpiresByType application/x-font-opentype "access plus 1 year"
	ExpiresByType application/font-ttf "access plus 1 year"
	ExpiresByType application/font-woff "access plus 1 year"
	ExpiresByType application/font-woff2 "access plus 1 year"
	ExpiresByType application/vnd.ms-fontobject "access plus 1 year"
	ExpiresByType application/font-sfnt "access plus 1 year"

	# CSS and JavaScript
	ExpiresByType text/css "access plus 1 year"
	ExpiresByType text/javascript "access plus 1 year"
	ExpiresByType application/javascript "access plus 1 year"

	<IfModule mod_headers.c>
		Header set Cache-Control "no-cache, no-store, must-revalidate"
		
		<FilesMatch "\.(js|css|ttf|woff2?|svg|png|jpe?g|webp|webm|mp4|ogg)(\.gz)?$">
			Header set Cache-Control "public"	
			Header set Vary: Accept-Encoding
		</FilesMatch>
		#Some server not properly recognizing WEBPs
		<FilesMatch "\.webp$">
			Header set Content-Type "image/webp"
			ExpiresDefault "access plus 1 year"
		</FilesMatch>	
	</IfModule>

</IfModule>

<IfModule mod_brotli.c>
	<IfModule mod_filter.c>
		AddOutputFilterByType BROTLI_COMPRESS text/html text/xml text/plain 
		AddOutputFilterByType BROTLI_COMPRESS application/rss+xml application/xml application/xhtml+xml 
		AddOutputFilterByType BROTLI_COMPRESS text/css 
		AddOutputFilterByType BROTLI_COMPRESS text/javascript application/javascript application/x-javascript 
		AddOutputFilterByType BROTLI_COMPRESS image/x-icon image/svg+xml
		AddOutputFilterByType BROTLI_COMPRESS application/rss+xml
		AddOutputFilterByType BROTLI_COMPRESS application/font application/font-truetype application/font-ttf
		AddOutputFilterByType BROTLI_COMPRESS application/font-otf application/font-opentype
		AddOutputFilterByType BROTLI_COMPRESS application/font-woff application/font-woff2
		AddOutputFilterByType BROTLI_COMPRESS application/vnd.ms-fontobject
		AddOutputFilterByType BROTLI_COMPRESS font/ttf font/otf font/opentype font/woff font/woff2
	</IfModule>
</IfModule>

<IfModule mod_deflate.c>
	<IfModule mod_filter.c>
		AddOutputFilterByType DEFLATE text/html text/xml text/plain 
		AddOutputFilterByType DEFLATE application/rss+xml application/xml application/xhtml+xml 
		AddOutputFilterByType DEFLATE text/css 
		AddOutputFilterByType DEFLATE text/javascript application/javascript application/x-javascript 
		AddOutputFilterByType DEFLATE image/x-icon image/svg+xml
		AddOutputFilterByType DEFLATE application/rss+xml
		AddOutputFilterByType DEFLATE application/font application/font-truetype application/font-ttf
		AddOutputFilterByType DEFLATE application/font-otf application/font-opentype
		AddOutputFilterByType DEFLATE application/font-woff application/font-woff2
		AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
		AddOutputFilterByType DEFLATE font/ttf font/otf font/opentype font/woff font/woff2
	</IfModule>
</IfModule>

# Don't compress files with extension .gz or .br
<IfModule mod_rewrite.c>
	RewriteRule "\.(gz|br)$" "-" [E=no-gzip:1,E=no-brotli:1]
</IfModule>

<IfModule !mod_rewrite.c>
	<IfModule mod_setenvif.c>
		SetEnvIfNoCase Request_URI \.(gz|br)$ no-gzip no-brotli
	</IfModule>
</IfModule>
$endLine

APACHECONFIG;

            $expires = str_replace(array("\r\n", "\n"), PHP_EOL, $expires);
            $str     = $expires . $cleanContents;

            $success = File::write($htaccess, $str);
            return null;
        } else {
            return 'FILEDOESNTEXIST';
        }
    }

    private static function getHtaccessRegex(): string
    {
        return '#[\r\n]*' . preg_quote(self::$startHtaccessLine) . '.*?' . preg_quote(
            rtrim(self::$endHtaccessLine, "# \n\r\t\v\x00")
        ) . '[^\r\n]*[\r\n]*#s';
    }

    public static function cleanHtaccess(): void
    {
        $htaccess = Paths::rootPath() . '/.htaccess';

        if (file_exists($htaccess)) {
            $contents      = file_get_contents($htaccess);
            $cleanContents = preg_replace(self::getHtaccessRegex(), '', $contents, -1, $count);

            if ($count > 0) {
                File::write($htaccess, $cleanContents);
            }
        }
    }


    /**
     * @return string|true
     *
     * @psalm-return 'BACKUPPATHDOESNTEXIST'|'SOMEIMAGESDIDNTRESTORE'|true
     */
    public static function restoreBackupImages(?LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }

        $backupPath = Paths::backupImagesParentDir() . OptimizeImage::$backup_folder_name;

        if (! is_dir($backupPath)) {
            return 'BACKUPPATHDOESNTEXIST';
        }

        $aFiles  = Folder::files($backupPath, '.', false, true, []);
        $failure = false;

        foreach ($aFiles as $backupContractedFile) {
            $success = false;

            $aPotentialOriginalFilePaths = [
                    AdminHelper::expandFileName($backupContractedFile),
                    AdminHelper::expandFileNameLegacy($backupContractedFile)
            ];

            foreach ($aPotentialOriginalFilePaths as $originalFilePath) {
                if (@file_exists($originalFilePath)) {
                    //Attempt to restore backup images
                    if (AdminHelper::copyImage($backupContractedFile, $originalFilePath)) {
                        try {
                            if (file_exists(Webp::getWebpPath($originalFilePath))) {
                                File::delete(Webp::getWebpPath($originalFilePath));
                            }

                            if (file_exists(Webp::getWebpPathLegacy($originalFilePath))) {
                                File::delete(Webp::getWebpPathLegacy($originalFilePath));
                            }

                            if (file_exists($backupContractedFile)) {
                                File::delete($backupContractedFile);
                            }

                            AdminHelper::unmarkOptimized($originalFilePath);
                            $success = true;
                            break;
                        } catch (FilesystemException $e) {
                            $logger->debug(
                                'Error deleting ' . Webp::getWebpPath(
                                    $originalFilePath
                                ) . ' with message: ' . $e->getMessage()
                            );
                        }
                    } else {
                        $logger->debug('Error copying image ' . $backupContractedFile);
                    }
                }
            }

            if (! $success) {
                $logger->debug('File not found: ' . $backupContractedFile);
                $logger->debug('Potential file paths: ' . print_r($aPotentialOriginalFilePaths, true));
                $failure = true;
            }
        }

        clearstatcache();

        if ($failure) {
            return 'SOMEIMAGESDIDNTRESTORE';
        } else {
            self::deleteBackupImages();
        }

        return true;
    }

    /**
     * @return bool|string
     *
     * @psalm-return 'BACKUPPATHDOESNTEXIST'|bool
     */
    public static function deleteBackupImages()
    {
        $backupPath = Paths::backupImagesParentDir() . OptimizeImage::$backup_folder_name;

        if (! is_dir($backupPath)) {
            return 'BACKUPPATHDOESNTEXIST';
        }

        return Folder::delete($backupPath);
    }

    public static function generateNewCacheKey(): void
    {
        $container = ContainerFactory::getContainer();
        $rand      = rand();
        /** @var Registry $params */
        $params = $container->get('params');
        $params->set('cache_random_key', $rand);
        Plugin::saveSettings($params);
    }
}

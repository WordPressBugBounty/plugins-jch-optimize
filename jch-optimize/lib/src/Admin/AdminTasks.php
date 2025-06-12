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

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Exception\FilesystemException;
use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Folder;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Log\NullLogger;
use JchOptimize\Core\Admin\AdminHelper as AdminHelper;
use JchOptimize\Core\Admin\Ajax\OptimizeImage;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\Htaccess;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\PluginInterface;
use JchOptimize\Core\Registry;

use function clearstatcache;
use function defined;
use function file_exists;
use function is_dir;
use function is_null;
use function print_r;
use function rand;

defined('_JCH_EXEC') or die('Restricted access');

class AdminTasks implements LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    public function __construct(
        private Registry $params,
        private AdminHelper $adminHelper,
        private PathsInterface $pathsUtils,
        private PluginInterface $pluginUtils
    ) {
    }

    public static string $startHtaccessLine = '## BEGIN EXPIRES CACHING - JCH OPTIMIZE ##';

    public static string $endHtaccessLine = '## END EXPIRES CACHING - JCH OPTIMIZE ##';

    public function leverageBrowserCaching(?bool &$success = null): ?string
    {
        $expires = <<<APACHECONFIG
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
		<FilesMatch "\.(js|css|ttf|woff2?|svg|png|jpe?g|webp|webm|mp4|ogg)(\.gz)?$">
			Header set Cache-Control "public"	
			Header set Vary: Accept-Encoding
		</FilesMatch>
		#Some server not properly recognizing WEBPs
		<FilesMatch "\.webp$">
			Header set Content-Type "image/webp"
			ExpiresDefault "access plus 1 year"
		</FilesMatch>	
		#Or font files
		<FilesMatch "\.woff2$">
		    Header set Content-Type "font/woff2"
		    ExpiresDefault "access plus 1 year"
        </FilesMatch>
        <FilesMatch "\.woff$">
            Header set Content-Type "font/woff"
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
APACHECONFIG;

        $expires = str_replace(array("\r\n", "\n"), PHP_EOL, $expires);

        try {
            $success = Htaccess::updateHtaccess(
                $this->pathsUtils,
                $expires,
                [self::$startHtaccessLine, self::$endHtaccessLine]
            );

            return null;
        } catch (Exception\FileNotFoundException $e) {
            return 'FILEDOESNTEXIST';
        }
    }

    public function cleanHtaccess(): void
    {
        Htaccess::cleanHtaccess($this->pathsUtils, [self::$startHtaccessLine, self::$endHtaccessLine]);
    }


    public function restoreBackupImages(): bool|string
    {
        if (is_null($this->logger)) {
            $this->logger = new NullLogger();
        }

        $backupPath = $this->getBackupImagesParentDir() . OptimizeImage::$backup_folder_name;

        if (!is_dir($backupPath)) {
            return 'BACKUPPATHDOESNTEXIST';
        }

        $aFiles = Folder::files($backupPath, '.', false, true, []);
        $failure = false;
        $webp = $this->getContainer()->get(Webp::class);

        foreach ($aFiles as $backupContractedFile) {
            $success = false;

            /** @var string[] $aPotentialOriginalFilePaths */
            $aPotentialOriginalFilePaths = [
                $this->adminHelper->expandFileName($backupContractedFile),
                $this->adminHelper->expandFileNameLegacy($backupContractedFile)
            ];

            foreach ($aPotentialOriginalFilePaths as $originalFilePath) {
                if (@file_exists($originalFilePath)) {
                    //Attempt to restore backup images
                    if ($this->adminHelper->copyImage($backupContractedFile, $originalFilePath)) {
                        try {
                            if (file_exists($webp->getWebpPath($originalFilePath))) {
                                File::delete($webp->getWebpPath($originalFilePath));
                            }

                            if (file_exists($webp->getWebpPathLegacy($originalFilePath))) {
                                File::delete($webp->getWebpPathLegacy($originalFilePath));
                            }

                            if (file_exists($backupContractedFile)) {
                                File::delete($backupContractedFile);
                            }

                            $this->adminHelper->unmarkOptimized($originalFilePath);
                            $success = true;
                            break;
                        } catch (FilesystemException $e) {
                            $this->logger->debug(
                                'Error deleting ' . $webp->getWebpPath(
                                    $originalFilePath
                                ) . ' with message: ' . $e->getMessage()
                            );
                        }
                    } else {
                        $this->logger->debug('Error copying image ' . $backupContractedFile);
                    }
                }
            }

            if (!$success) {
                $this->logger->debug('File not found: ' . $backupContractedFile);
                $this->logger->debug('Potential file paths: ' . print_r($aPotentialOriginalFilePaths, true));
                $failure = true;
            }
        }

        clearstatcache();

        if ($failure) {
            return 'SOMEIMAGESDIDNTRESTORE';
        } else {
            $this->deleteBackupImages();
        }

        return true;
    }

    public function deleteBackupImages(): bool|string
    {
        $backupPath = $this->getBackupImagesParentDir() . OptimizeImage::$backup_folder_name;

        if (!is_dir($backupPath)) {
            return 'BACKUPPATHDOESNTEXIST';
        }

        return Folder::delete($backupPath);
    }

    public function generateNewCacheKey(): void
    {
        $rand = rand();
        $this->params->set('cache_random_key', $rand);
        $this->pluginUtils->saveSettings($this->params);
    }

    private function getBackupImagesParentDir(): string
    {
        return $this->pathsUtils->backupImagesParentDir();
    }
}

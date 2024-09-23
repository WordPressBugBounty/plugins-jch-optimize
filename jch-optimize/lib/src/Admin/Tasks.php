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

namespace JchOptimize\Core\Admin;

use _JchOptimizeVendor\Joomla\Filesystem\Exception\FilesystemException;
use _JchOptimizeVendor\Joomla\Filesystem\File;
use _JchOptimizeVendor\Joomla\Filesystem\Folder;
use _JchOptimizeVendor\Psr\Log\LoggerInterface;
use _JchOptimizeVendor\Psr\Log\NullLogger;
use JchOptimize\ContainerFactory;
use JchOptimize\Core\Admin\Ajax\OptimizeImage;
use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\Htaccess;
use JchOptimize\Core\Registry;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Plugin;

\defined('_JCH_EXEC') or exit('Restricted access');
class Tasks
{
    public static string $startHtaccessLine = '## BEGIN EXPIRES CACHING - JCH OPTIMIZE ##';
    public static string $endHtaccessLine = '## END EXPIRES CACHING - JCH OPTIMIZE ##';

    /**
     * @return ?string
     */
    public static function leverageBrowserCaching(?bool &$success = null): ?string
    {
        $expires = <<<APACHECONFIG
<IfModule mod_expires.c>
\tExpiresActive on

\t# Your document html
\tExpiresByType text/html "access plus 0 seconds"

\t# Data
\tExpiresByType text/xml "access plus 0 seconds"
\tExpiresByType application/xml "access plus 0 seconds"
\tExpiresByType application/json "access plus 0 seconds"

\t# Feed
\tExpiresByType application/rss+xml "access plus 1 hour"
\tExpiresByType application/atom+xml "access plus 1 hour"

\t# Favicon (cannot be renamed)
\tExpiresByType image/x-icon "access plus 1 week"

\t# Media: images, video, audio
\tExpiresByType image/gif "access plus 1 year"
\tExpiresByType image/png "access plus 1 year"
\tExpiresByType image/jpg "access plus 1 year"
\tExpiresByType image/jpeg "access plus 1 year"
\tExpiresByType image/webp "access plus 1 year"
\tExpiresByType audio/ogg "access plus 1 year"
\tExpiresByType video/ogg "access plus 1 year"
\tExpiresByType video/mp4 "access plus 1 year"
\tExpiresByType video/webm "access plus 1 year"

\t# HTC files (css3pie)
\tExpiresByType text/x-component "access plus 1 year"

\t# Webfonts
\tExpiresByType image/svg+xml "access plus 1 year"
\tExpiresByType font/* "access plus 1 year"
\tExpiresByType application/x-font-ttf "access plus 1 year"
\tExpiresByType application/x-font-truetype "access plus 1 year"
\tExpiresByType application/x-font-opentype "access plus 1 year"
\tExpiresByType application/font-ttf "access plus 1 year"
\tExpiresByType application/font-woff "access plus 1 year"
\tExpiresByType application/font-woff2 "access plus 1 year"
\tExpiresByType application/vnd.ms-fontobject "access plus 1 year"
\tExpiresByType application/font-sfnt "access plus 1 year"

\t# CSS and JavaScript
\tExpiresByType text/css "access plus 1 year"
\tExpiresByType text/javascript "access plus 1 year"
\tExpiresByType application/javascript "access plus 1 year"

\t<IfModule mod_headers.c>
\t\tHeader set Cache-Control "no-cache, max-age=0, must-revalidate"
\t\t
\t\t<FilesMatch "\\.(js|css|ttf|woff2?|svg|png|jpe?g|webp|webm|mp4|ogg)(\\.gz)?\$">
\t\t\tHeader set Cache-Control "public"\t
\t\t\tHeader set Vary: Accept-Encoding
\t\t</FilesMatch>
\t\t#Some server not properly recognizing WEBPs
\t\t<FilesMatch "\\.webp\$">
\t\t\tHeader set Content-Type "image/webp"
\t\t\tExpiresDefault "access plus 1 year"
\t\t</FilesMatch>\t
\t\t#Or font files
\t\t<FilesMatch "\\.woff2\$">
\t\t    Header set Content-Type "font/woff2"
\t\t    ExpiresDefault "access plus 1 year"
        </FilesMatch>
        <FilesMatch "\\.woff\$">
            Header set Content-Type "font/woff"
            ExpiresDefault "access plus 1 year"
        </FilesMatch>
\t</IfModule>
</IfModule>

<IfModule mod_brotli.c>
\t<IfModule mod_filter.c>
\t\tAddOutputFilterByType BROTLI_COMPRESS text/html text/xml text/plain 
\t\tAddOutputFilterByType BROTLI_COMPRESS application/rss+xml application/xml application/xhtml+xml 
\t\tAddOutputFilterByType BROTLI_COMPRESS text/css 
\t\tAddOutputFilterByType BROTLI_COMPRESS text/javascript application/javascript application/x-javascript 
\t\tAddOutputFilterByType BROTLI_COMPRESS image/x-icon image/svg+xml
\t\tAddOutputFilterByType BROTLI_COMPRESS application/rss+xml
\t\tAddOutputFilterByType BROTLI_COMPRESS application/font application/font-truetype application/font-ttf
\t\tAddOutputFilterByType BROTLI_COMPRESS application/font-otf application/font-opentype
\t\tAddOutputFilterByType BROTLI_COMPRESS application/font-woff application/font-woff2
\t\tAddOutputFilterByType BROTLI_COMPRESS application/vnd.ms-fontobject
\t\tAddOutputFilterByType BROTLI_COMPRESS font/ttf font/otf font/opentype font/woff font/woff2
\t</IfModule>
</IfModule>

<IfModule mod_deflate.c>
\t<IfModule mod_filter.c>
\t\tAddOutputFilterByType DEFLATE text/html text/xml text/plain 
\t\tAddOutputFilterByType DEFLATE application/rss+xml application/xml application/xhtml+xml 
\t\tAddOutputFilterByType DEFLATE text/css 
\t\tAddOutputFilterByType DEFLATE text/javascript application/javascript application/x-javascript 
\t\tAddOutputFilterByType DEFLATE image/x-icon image/svg+xml
\t\tAddOutputFilterByType DEFLATE application/rss+xml
\t\tAddOutputFilterByType DEFLATE application/font application/font-truetype application/font-ttf
\t\tAddOutputFilterByType DEFLATE application/font-otf application/font-opentype
\t\tAddOutputFilterByType DEFLATE application/font-woff application/font-woff2
\t\tAddOutputFilterByType DEFLATE application/vnd.ms-fontobject
\t\tAddOutputFilterByType DEFLATE font/ttf font/otf font/opentype font/woff font/woff2
\t</IfModule>
</IfModule>

# Don't compress files with extension .gz or .br
<IfModule mod_rewrite.c>
\tRewriteRule "\\.(gz|br)\$" "-" [E=no-gzip:1,E=no-brotli:1]
</IfModule>

<IfModule !mod_rewrite.c>
\t<IfModule mod_setenvif.c>
\t\tSetEnvIfNoCase Request_URI \\.(gz|br)\$ no-gzip no-brotli
\t</IfModule>
</IfModule>
APACHECONFIG;
        $expires = \str_replace(["\r\n", "\n"], \PHP_EOL, $expires);

        try {
            $success = Htaccess::updateHtaccess($expires, [self::$startHtaccessLine, self::$endHtaccessLine]);

            return null;
        } catch (Exception\FileNotFoundException $e) {
            return 'FILEDOESNTEXIST';
        }
    }

    public static function cleanHtaccess(): void
    {
        Htaccess::cleanHtaccess([self::$startHtaccessLine, self::$endHtaccessLine]);
    }

    public static function restoreBackupImages(?LoggerInterface $logger = null): bool|string
    {
        if (\is_null($logger)) {
            $logger = new NullLogger();
        }
        $backupPath = Paths::backupImagesParentDir().OptimizeImage::$backup_folder_name;
        if (!\is_dir($backupPath)) {
            return 'BACKUPPATHDOESNTEXIST';
        }
        $aFiles = Folder::files($backupPath, '.', \false, \true, []);
        $failure = \false;
        foreach ($aFiles as $backupContractedFile) {
            $success = \false;

            /** @var string[] $aPotentialOriginalFilePaths */
            $aPotentialOriginalFilePaths = [AdminHelper::expandFileName($backupContractedFile), AdminHelper::expandFileNameLegacy($backupContractedFile)];
            foreach ($aPotentialOriginalFilePaths as $originalFilePath) {
                if (@\file_exists($originalFilePath)) {
                    // Attempt to restore backup images
                    if (AdminHelper::copyImage($backupContractedFile, $originalFilePath)) {
                        try {
                            if (\file_exists(Webp::getWebpPath($originalFilePath))) {
                                File::delete(Webp::getWebpPath($originalFilePath));
                            }
                            if (\file_exists(Webp::getWebpPathLegacy($originalFilePath))) {
                                File::delete(Webp::getWebpPathLegacy($originalFilePath));
                            }
                            if (\file_exists($backupContractedFile)) {
                                File::delete($backupContractedFile);
                            }
                            AdminHelper::unmarkOptimized($originalFilePath);
                            $success = \true;

                            break;
                        } catch (FilesystemException $e) {
                            $logger->debug('Error deleting '.Webp::getWebpPath($originalFilePath).' with message: '.$e->getMessage());
                        }
                    } else {
                        $logger->debug('Error copying image '.$backupContractedFile);
                    }
                }
            }
            if (!$success) {
                $logger->debug('File not found: '.$backupContractedFile);
                $logger->debug('Potential file paths: '.\print_r($aPotentialOriginalFilePaths, \true));
                $failure = \true;
            }
        }
        \clearstatcache();
        if ($failure) {
            return 'SOMEIMAGESDIDNTRESTORE';
        }
        self::deleteBackupImages();

        return \true;
    }

    /**
     * @return bool|string
     *
     * @psalm-return 'BACKUPPATHDOESNTEXIST'|bool
     */
    public static function deleteBackupImages()
    {
        $backupPath = Paths::backupImagesParentDir().OptimizeImage::$backup_folder_name;
        if (!\is_dir($backupPath)) {
            return 'BACKUPPATHDOESNTEXIST';
        }

        return Folder::delete($backupPath);
    }

    public static function generateNewCacheKey(): void
    {
        $container = ContainerFactory::getContainer();
        $rand = \rand();

        /** @var Registry $params */
        $params = $container->get('params');
        $params->set('cache_random_key', $rand);
        Plugin::saveSettings($params);
    }
}

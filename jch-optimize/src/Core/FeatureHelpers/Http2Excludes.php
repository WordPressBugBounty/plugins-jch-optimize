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

namespace JchOptimize\Core\FeatureHelpers;

use JchOptimize\Core\Helper;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Core\Uri\Utils;
use Joomla\DI\Container;
use Joomla\Registry\Registry;
use Psr\Http\Message\UriInterface;
use function defined;
use function preg_match;

defined('_JCH_EXEC') or die('Restricted access');

class Http2Excludes extends AbstractFeatureHelper
{
    /**
     * @var Http2Preload
     */
    private Http2Preload $http2Preload;

    public function __construct(Container $container, Registry $params, Http2Preload $http2Preload)
    {
        parent::__construct($container, $params);

        $this->http2Preload = $http2Preload;
    }

    public function addHttp2Includes(): void
    {
        /** @var string[] $aIncludeFiles */
        $aIncludeFiles = $this->params->get('pro_http2_include', array());

        if (empty($aIncludeFiles)) {
            return;
        }

        foreach ($aIncludeFiles as $sIncludeFile) {
            preg_match("#\.\K(?:js|css|webp|gif|jpe?g|png|woff2?|ttf)(?=$|[\#?])#i", $sIncludeFile, $aM);

            switch ($aM[0]) {
                case  'js':
                    $type = 'script';
                    break;
                case 'css':
                    $type = 'style';
                    break;
                case 'woff':
                case 'woff2':
                case 'ttf':
                    $type = 'font';
                    break;
                case 'webp':
                case 'gif':
                case 'jpg':
                case 'jpeg':
                case 'png':
                    $type = 'image';
                    break;
                default:
                    $type = '';
                    break;
            }

            if ($type != '') {
                $this->http2Preload->addAdditional(Utils::uriFor($sIncludeFile), $type, $aM[0]);
            }
        }
    }

    public function findHttp2Excludes(UriInterface $uri, bool $bDeferred): bool
    {
        if (Helper::findExcludes($this->params->get('pro_http2_exclude', []), (string)$uri)) {
            return true;
        }

        //If file is marked deferred when 'Exclude deferred' is enabled, return
        if ($this->params->get('pro_http2_exclude_deferred', '1') && $bDeferred) {
            return true;
        }

        return false;
    }
}

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

namespace JchOptimize\Core\Service;

use JchOptimize\Core\Cdn;
use JchOptimize\Core\Css\Callbacks\CombineMediaQueries;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Callbacks\ExtractCriticalCss;
use JchOptimize\Core\Css\Callbacks\FormatCss;
use JchOptimize\Core\Css\Callbacks\HandleAtRules;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Html\Callbacks\Cdn as CdnCallback;
use JchOptimize\Core\Html\Callbacks\CombineJsCss;
use JchOptimize\Core\Html\Callbacks\LazyLoad;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Core\Http2Preload;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

use Joomla\Registry\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class CallbackProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        //Html callback
        $container->protect(CdnCallback::class, [$this, 'getCdnCallbackService']);
        $container->protect(CombineJsCss::class, [$this, 'getCombineJsCssService']);
        $container->protect(LazyLoad::class, [$this, 'getLazyLoadService']);
        //Css Callback;
        $container->protect(CombineMediaQueries::class, [$this, 'getCombineMediaQueriesService']);
        $container->protect(CorrectUrls::class, [$this, 'getCorrectUrlsService']);
        $container->protect(ExtractCriticalCss::class, [$this, 'getExtractCriticalCssService']);
        $container->protect(FormatCss::class, [$this, 'getFormatCssService']);
        $container->protect(HandleAtRules::class, [$this, 'getHandleAtRulesService']);
    }

    public function getCdnCallbackService(Container $container): CdnCallback
    {
        return new CdnCallback(
            $container,
            $container->get(Registry::class),
            $container->get(Cdn::class)
        );
    }

    public function getCombineJsCssService(Container $container): CombineJsCss
    {
        return new CombineJsCss(
            $container,
            $container->get(Registry::class),
            $container->get(FilesManager::class),
            $container->get(Http2Preload::class),
            $container->get(HtmlProcessor::class)
        );
    }

    public function getLazyLoadService(Container $container): LazyLoad
    {
        return new LazyLoad(
            $container,
            $container->get(Registry::class),
            $container->get(Http2Preload::class)
        );
    }

    public function getCombineMediaQueriesService(Container $container): CombineMediaQueries
    {
        return new CombineMediaQueries(
            $container,
            $container->get(Registry::class)
        );
    }

    public function getCorrectUrlsService(Container $container): CorrectUrls
    {
        return new CorrectUrls(
            $container,
            $container->get(Registry::class),
            $container->get(Cdn::class),
            $container->get(Http2Preload::class)
        );
    }

    public function getExtractCriticalCssService(Container $container): ExtractCriticalCss
    {
        return new ExtractCriticalCss(
            $container,
            $container->get(Registry::class)
        );
    }

    public function getFormatCssService(Container $container): FormatCss
    {
        return new FormatCss(
            $container,
            $container->get(Registry::class)
        );
    }

    public function getHandleAtRulesService(Container $container): HandleAtRules
    {
        return new HandleAtRules(
            $container,
            $container->get(Registry::class)
        );
    }
}

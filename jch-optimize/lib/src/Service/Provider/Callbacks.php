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

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDependencies;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDomainProfiler;
use JchOptimize\Core\Css\Callbacks\ExtractCriticalCss;
use JchOptimize\Core\Css\Callbacks\FormatCss;
use JchOptimize\Core\Css\Callbacks\HandleAtRules;
use JchOptimize\Core\Css\Callbacks\PostProcessCriticalCss;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\FeatureHelpers\DynamicSelectors;
use JchOptimize\Core\Html\Callbacks\Cdn as CdnCallback;
use JchOptimize\Core\Html\Callbacks\CombineJsCss;
use JchOptimize\Core\Html\Callbacks\JavaScriptConfigureHelper;
use JchOptimize\Core\Html\Callbacks\LazyLoad;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Html\JsLayout\JsLayoutPlanner;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class Callbacks implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        //Html callback
        $container->set(CdnCallback::class, [$this, 'getCdnCallbackService']);
        $container->set(CombineJsCss::class, [$this, 'getCombineJsCssService']);
        $container->set(LazyLoad::class, [$this, 'getLazyLoadService']);
        $container->set(JavaScriptConfigureHelper::class, [$this, 'getJavaScriptConfigureHelperService']);
        //Css Callback;
        $container->set(CorrectUrls::class, [$this, 'getCorrectUrlsService']);
        $container->set(ExtractCriticalCss::class, [$this, 'getExtractCriticalCssService']);
        $container->set(FormatCss::class, [$this, 'getFormatCssService']);
        $container->set(HandleAtRules::class, [$this, 'getHandleAtRulesService']);
        $container->set(PostProcessCriticalCss::class, [$this, 'getPostProcessCriticalCssService']);

        $container->share(CriticalCssDependencies::class, [$this, 'getDependenciesProviderService']);
        $container->share(CriticalCssDomainProfiler::class, [$this, 'getCriticalCssDomainProfilerService']);
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
            $container->get(HtmlProcessor::class),
            $container->get(ProfilerInterface::class),
            $container->get(ExcludesInterface::class)
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

    public function getJavaScriptConfigureHelperService(Container $container): JavaScriptConfigureHelper
    {
        return new JavaScriptConfigureHelper(
            $container,
            $container->get(Registry::class),
            $container->get(FilesManager::class),
            $container->get(HtmlProcessor::class),
            $container->get(ProfilerInterface::class),
            $container->get(ExcludesInterface::class),
            $container->get(JsLayoutPlanner::class),
            $container->get(DynamicJs::class)
        );
    }

    public function getCorrectUrlsService(Container $container): CorrectUrls
    {
        return new CorrectUrls(
            $container,
            $container->get(Registry::class),
            $container->get(Cdn::class),
            $container->get(Http2Preload::class),
            $container->get(UtilityInterface::class)
        );
    }

    public function getExtractCriticalCssService(Container $container): ExtractCriticalCss
    {
        return new ExtractCriticalCss(
            $container,
            $container->get(Registry::class),
            $container->get(CriticalCssDependencies::class),
            $container->get(DynamicSelectors::class),
            $container->get(CorrectUrls::class)
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

    public function getPostProcessCriticalCssService(Container $container): PostProcessCriticalCss
    {
        return new PostProcessCriticalCss(
            $container,
            $container->get(Registry::class),
            $container->get(CriticalCssDependencies::class)
        );
    }

    public function getDependenciesProviderService(Container $container): CriticalCssDependencies
    {
        $deps = new CriticalCssDependencies($container->get(HtmlProcessor::class));

        if (getenv('JCH_CRITICAL_PROFILER') === '1') {
            $deps->setProfiler($container->get(CriticalCssDomainProfiler::class));
        }

        return $deps;
    }

    public function getCriticalCssDomainProfilerService(Container $container): CriticalCssDomainProfiler
    {
        return new CriticalCssDomainProfiler();
    }
}

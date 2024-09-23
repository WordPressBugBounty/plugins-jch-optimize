<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Service;

use JchOptimize\Controller\ApplyAutoSetting;
use JchOptimize\Controller\BrowserCaching;
use JchOptimize\Controller\CleanCache;
use JchOptimize\Controller\Configurations;
use JchOptimize\Controller\DeleteBackups;
use JchOptimize\Controller\ExportSettings;
use JchOptimize\Controller\GetCacheInfo;
use JchOptimize\Controller\Help;
use JchOptimize\Controller\ImportSettings;
use JchOptimize\Controller\KeyCache;
use JchOptimize\Controller\Main;
use JchOptimize\Controller\OptimizeImages;
use JchOptimize\Controller\OrderPlugins;
use JchOptimize\Controller\PageCache;
use JchOptimize\Controller\ReCache;
use JchOptimize\Controller\RestoreImages;
use JchOptimize\Controller\SetDefaultSettings;
use JchOptimize\Controller\ToggleSetting;
use JchOptimize\ControllerResolver;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Container\Container;
use JchOptimize\Core\Container\ServiceProviderInterface;
use JchOptimize\Core\Input;
use JchOptimize\Core\Interfaces\MvcLoggerInterface;
use JchOptimize\Core\Mvc\View;
use JchOptimize\Core\PageCache\PageCache as CorePageCache;
use JchOptimize\Core\Registry;
use JchOptimize\Log\WordpressNoticeLogger;
use JchOptimize\Model\BulkSettings;
use JchOptimize\Model\Cache;
use JchOptimize\Model\Configure;
use JchOptimize\Model\PageCache as PageCacheModel;
use JchOptimize\Model\ReCache as ReCacheModel;
use JchOptimize\Plugin\Loader;
use JchOptimize\View\ConfigurationsHtml;
use JchOptimize\View\MainHtml;
use JchOptimize\View\PageCacheHtml;

class MvcProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        //MVC dependencies
        $container->alias(MvcLoggerInterface::class, WordpressNoticeLogger::class)
                  ->share(WordpressNoticeLogger::class, [$this, 'getWordpressNoticeLoggerService'], true);
        $container->share(ControllerResolver::class, [$this, 'getControllerResolverService'], true);

        //controllers
        $container->alias(ApplyAutoSetting::class, 'applyautosetting')
                  ->share('applyautosetting', [$this, 'getControllerApplyAutoSettingService'], true);

        $container->alias(BrowserCaching::class, 'browsercaching')
                  ->share('browsercaching', [$this, 'getControllerBrowserCachingService'], true);

        $container->alias(CleanCache::class, 'cleancache')
                  ->share('cleancache', [$this, 'getControllerCleanCacheService'], true);

        $container->alias(Configurations::class, 'configurations')
                  ->share('configurations', [$this, 'getControllerConfigurationsService'], true);

        $container->alias(DeleteBackups::class, 'deletebackups')
                  ->share('deletebackups', [$this, 'getControllerDeleteBackupsService'], true);

        $container->alias(Help::class, 'help')
                  ->share('help', [$this, 'getControllerHelpService'], true);

        $container->alias(KeyCache::class, 'keycache')
                  ->share('keycache', [$this, 'getControllerKeyCacheService'], true);

        $container->alias(Main::class, 'main')
                  ->share('main', [$this, 'getControllerMainService'], true);

        $container->alias(OptimizeImages::class, 'optimizeimages')
                  ->share('optimizeimages', [$this, 'getControllerOptimizeImagesService'], true);

        $container->alias(OrderPlugins::class, 'orderplugins')
                  ->share('orderplugins', [$this, 'getControllerOrderPluginsService'], true);

        $container->alias(RestoreImages::class, 'restoreimages')
                  ->share('restoreimages', [$this, 'getControllerRestoreImagesService'], true);

        $container->alias(ToggleSetting::class, 'togglesetting')
                  ->share('togglesetting', [$this, 'getControllerToggleSettingService'], true);

        $container->alias(PageCache::class, 'pagecache')
                  ->share('pagecache', [$this, 'getControllerPageCacheService'], true);
        $container->alias(ReCache::class, 'recache')
                  ->share('recache', [$this, 'getControllerReCacheService'], true);

        $container->alias(SetDefaultSettings::class, 'setdefaultsettings')
                  ->share('setdefaultsettings', [$this, 'getControllerSetDefaultSettingsService'], true);

        $container->alias(ExportSettings::class, 'exportsettings')
                  ->share('exportsettings', [$this, 'getControllerExportSettingsService'], true);

        $container->alias(ImportSettings::class, 'importsettings')
                  ->share('importsettings', [$this, 'getControllerImportSettingsService'], true);

        $container->alias(GetCacheInfo::class, 'getcacheinfo')
                  ->share('getcacheinfo', [$this, 'getControllerGetCacheInfoService'], true);

        //Models
        $container->share(Configure::class, [$this, 'getModelConfigureService'], true);
        $container->share(Cache::class, [$this, 'getModelMainService'], true);
        $container->share(PageCacheModel::class, [$this, 'getModelPageCacheModelService'], true);
        $container->share(ReCacheModel::class, [$this, 'getModelReCacheModelService'], true);
        $container->share(BulkSettings::class, [$this, 'getModelBulkSettingsService'], true);

        //Views
        $container->share(View::class, [$this, 'getViewHtmlService'], true);
        $container->share(MainHtml::class, [$this, 'getViewMainHtmlService'], true);
        $container->share(ConfigurationsHtml::class, [$this, 'getViewConfigurationsHtmlService'], true);
        $container->share(PageCacheHtml::class, [$this, 'getViewPageCacheHtmlService'], true);
    }

    public function getWordpressNoticeLoggerService(): WordpressNoticeLogger
    {
        return new WordpressNoticeLogger();
    }

    public function getControllerResolverService(Container $container): ControllerResolver
    {
        return new ControllerResolver(
            $container,
            $container->get(Input::class)
        );
    }

    public function getControllerApplyAutoSettingService(Container $container): ApplyAutoSetting
    {
        $controller = new ApplyAutoSetting(
            $container->get(Configure::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerBrowserCachingService(Container $container): BrowserCaching
    {
        $controller = new BrowserCaching(
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerCleanCacheService(Container $container): CleanCache
    {
        $controller = new CleanCache(
            $container->get(Cache::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerConfigurationsService(Container $container): Configurations
    {
        return new Configurations(
            $container->get(ConfigurationsHtml::class),
            $container->get(Input::class)
        );
    }

    public function getControllerDeleteBackupsService(Container $container): DeleteBackups
    {
        $controller = new DeleteBackups(
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerHelpService(Container $container): Help
    {
        return new Help(
            $container->get(View::class),
            $container->get(Input::class)
        );
    }

    public function getControllerKeyCacheService(Container $container): KeyCache
    {
        $controller = new KeyCache(
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerMainService(Container $container): Main
    {
        return new Main(
            $container->get(MainHtml::class),
            $container->get(Icons::class),
            $container->get(Input::class)
        );
    }

    public function getControllerOptimizeImagesService(Container $container): OptimizeImages
    {
        $controller = new OptimizeImages(
            $container->get(View::class),
            $container->get(Icons::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerOrderPluginsService(Container $container): OrderPlugins
    {
        $controller = new OrderPlugins(
            $container->get(Loader::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerRestoreImagesService(Container $container): RestoreImages
    {
        $controller = new RestoreImages(
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerToggleSettingService(Container $container): ToggleSetting
    {
        $controller = new ToggleSetting(
            $container->get(Configure::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerPageCacheService(Container $container): PageCache
    {
        $controller = (new PageCache(
            $container->get(PageCacheHtml::class),
            $container->get(PageCacheModel::class),
            $container->get(Input::class)
        ))->setContainer($container);

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerReCacheService(Container $container): ReCache
    {
        return new ReCache(
            $container->get(ReCacheModel::class),
        );
    }

    public function getControllerSetDefaultSettingsService(Container $container): SetDefaultSettings
    {
        $controller = new SetDefaultSettings(
            $container->get(BulkSettings::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerExportSettingsService(Container $container): ExportSettings
    {
        $controller = new ExportSettings(
            $container->get(BulkSettings::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerImportSettingsService(Container $container): ImportSettings
    {
        $controller = new ImportSettings(
            $container->get(BulkSettings::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerGetCacheInfoService(Container $container): GetCacheInfo
    {
        return new GetCacheInfo(
            $container->get(Cache::class),
            $container->get(Input::class)
        );
    }

    public function getModelConfigureService(Container $container): Configure
    {
        $model = new Configure();
        $model->setState($container->get(Registry::class));
        $model->setContainer($container);

        return $model;
    }

    public function getModelMainService(Container $container): Cache
    {
        return (new Cache(
            $container->get(CorePageCache::class),
        ))->setContainer($container);
    }

    public function getModelPageCacheModelService(Container $container): PageCacheModel
    {
        return new PageCacheModel(
            $container->get(Input::class),
            $container->get(CorePageCache::class),
            $container
        );
    }

    public function getModelReCacheModelService(Container $container): ReCacheModel
    {
        $reCacheModel = new ReCacheModel();
        $reCacheModel->setLogger($container->get(WordpressNoticeLogger::class));

        return $reCacheModel;
    }

    public function getModelBulKSettingsService(Container $container): BulkSettings
    {
        $model = new BulkSettings();
        $model->setState($container->get(Registry::class));

        return $model;
    }

    public function getViewHtmlService(Container $container): View
    {
        $renderer = $container->get('renderer');
        $renderer->getRenderer()->setLayout('template.php');

        $view = new View($container->get('renderer'));

        $layout = $container->get(Input::class)->get('tab', 'main') . '.php';
        $view->setLayout($layout);

        return $view;
    }

    public function getViewMainHtmlService(Container $container): MainHtml
    {
        $renderer = $container->get('renderer');
        $renderer->getRenderer()->setLayout('template.php');

        return (new MainHtml(
            $renderer
        ))->setLayout('main.php');
    }

    public function getViewConfigurationsHtmlService(Container $container): ConfigurationsHtml
    {
        $renderer = $container->get('renderer');
        $renderer->getRenderer()->setLayout('template.php');

        return (new ConfigurationsHtml(
            $renderer
        ))->setLayout('configurations.php');
    }

    public function getViewPageCacheHtmlService(Container $container): PageCacheHtml
    {
        $renderer = $container->get('renderer');
        $renderer->getRenderer()->setLayout('template.php');

        return (new PageCacheHtml(
            $renderer
        ))->setLayout('pagecache.php');
    }
}

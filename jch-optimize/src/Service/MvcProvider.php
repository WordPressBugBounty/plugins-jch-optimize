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

namespace JchOptimize\WordPress\Service;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Joomla\Input\Input;
use _JchOptimizeVendor\V91\Joomla\Renderer\RendererInterface;
use _JchOptimizeVendor\V91\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use _JchOptimizeVendor\V91\Slim\Views\PhpRenderer;
use JchOptimize\Core\Admin\AdminTasks;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Model\CacheMaintainer;
use JchOptimize\Core\Mvc\Renderer;
use JchOptimize\Core\Mvc\View;
use JchOptimize\Core\PageCache\PageCache as CorePageCache;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Registry;
use JchOptimize\WordPress\Controller\ApplyAutoSetting;
use JchOptimize\WordPress\Controller\BrowserCaching;
use JchOptimize\WordPress\Controller\CfVerifyToken;
use JchOptimize\WordPress\Controller\CleanCache;
use JchOptimize\WordPress\Controller\Configurations;
use JchOptimize\WordPress\Controller\DeleteBackups;
use JchOptimize\WordPress\Controller\ExportSettings;
use JchOptimize\WordPress\Controller\GetCacheInfo;
use JchOptimize\WordPress\Controller\Help;
use JchOptimize\WordPress\Controller\ImportSettings;
use JchOptimize\WordPress\Controller\KeyCache;
use JchOptimize\WordPress\Controller\Main;
use JchOptimize\WordPress\Controller\OptimizeImages;
use JchOptimize\WordPress\Controller\OrderPlugins;
use JchOptimize\WordPress\Controller\PageCache;
use JchOptimize\WordPress\Controller\ReCache;
use JchOptimize\WordPress\Controller\RestoreImages;
use JchOptimize\WordPress\Controller\SetDefaultSettings;
use JchOptimize\WordPress\Controller\ToggleSetting;
use JchOptimize\WordPress\ControllerResolver;
use JchOptimize\WordPress\Log\WordpressNoticeLogger;
use JchOptimize\WordPress\Model\BulkSettings;
use JchOptimize\WordPress\Model\Configure;
use JchOptimize\WordPress\Model\NotificationIcons;
use JchOptimize\WordPress\Model\PageCache as PageCacheModel;
use JchOptimize\WordPress\Model\ReCache as ReCacheModel;
use JchOptimize\WordPress\Plugin\Loader;
use JchOptimize\WordPress\Plugin\Updater;
use JchOptimize\WordPress\View\CfVerifyJson;
use JchOptimize\WordPress\View\ConfigurationsHtml;
use JchOptimize\WordPress\View\MainHtml;
use JchOptimize\WordPress\View\OptimizeImageHtml;
use JchOptimize\WordPress\View\PageCacheHtml;

class MvcProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        //MVC dependencies
        $container->share(WordpressNoticeLogger::class, [$this, 'getWordpressNoticeLoggerService']);
        $container->share(ControllerResolver::class, [$this, 'getControllerResolverService']);

        //controllers
        $container->alias(ApplyAutoSetting::class, 'applyautosetting')
                  ->share('applyautosetting', [$this, 'getControllerApplyAutoSettingService']);
        $container->alias(BrowserCaching::class, 'browsercaching')
                  ->share('browsercaching', [$this, 'getControllerBrowserCachingService']);
        $container->alias(CleanCache::class, 'cleancache')
                  ->share('cleancache', [$this, 'getControllerCleanCacheService']);
        $container->alias(Configurations::class, 'configurations')
                  ->share('configurations', [$this, 'getControllerConfigurationsService']);
        $container->alias(DeleteBackups::class, 'deletebackups')
                  ->share('deletebackups', [$this, 'getControllerDeleteBackupsService']);
        $container->alias(Help::class, 'help')
                  ->share('help', [$this, 'getControllerHelpService']);
        $container->alias(KeyCache::class, 'keycache')
                  ->share('keycache', [$this, 'getControllerKeyCacheService']);
        $container->alias(Main::class, 'main')
                  ->share('main', [$this, 'getControllerMainService']);
        $container->alias(OptimizeImages::class, 'optimizeimages')
                  ->share('optimizeimages', [$this, 'getControllerOptimizeImagesService']);
        $container->alias(OrderPlugins::class, 'orderplugins')
                  ->share('orderplugins', [$this, 'getControllerOrderPluginsService']);
        $container->alias(RestoreImages::class, 'restoreimages')
                  ->share('restoreimages', [$this, 'getControllerRestoreImagesService']);
        $container->alias(ToggleSetting::class, 'togglesetting')
                  ->share('togglesetting', [$this, 'getControllerToggleSettingService']);
        $container->alias(PageCache::class, 'pagecache')
                  ->share('pagecache', [$this, 'getControllerPageCacheService']);
        $container->alias(SetDefaultSettings::class, 'setdefaultsettings')
                  ->share('setdefaultsettings', [$this, 'getControllerSetDefaultSettingsService']);
        $container->alias(ExportSettings::class, 'exportsettings')
                  ->share('exportsettings', [$this, 'getControllerExportSettingsService']);
        $container->alias(ImportSettings::class, 'importsettings')
                  ->share('importsettings', [$this, 'getControllerImportSettingsService']);
        $container->alias(GetCacheInfo::class, 'getcacheinfo')
                  ->share('getcacheinfo', [$this, 'getControllerGetCacheInfoService']);

        //Models
        $container->share(Configure::class, [$this, 'getModelConfigureService']);
        $container->share(PageCacheModel::class, [$this, 'getModelPageCacheModelService']);
        $container->share(BulkSettings::class, [$this, 'getModelBulkSettingsService']);
        $container->share(NotificationIcons::class, [$this, 'getModelNotificationIconsService']);

        //Views
        $container->share(View::class, [$this, 'getViewHtmlService']);
        $container->share(MainHtml::class, [$this, 'getViewMainHtmlService']);
        $container->share(ConfigurationsHtml::class, [$this, 'getViewConfigurationsHtmlService']);
        $container->share(PageCacheHtml::class, [$this, 'getViewPageCacheHtmlService']);
        $container->share(OptimizeImageHtml::class, [$this, 'getViewOptimizeImageHtmlService']);

        //Renderer
        $container->share(RendererInterface::class, [$this, 'getRendererService']);

        if (JCH_PRO) {
            $container->alias(ReCache::class, 'recache')
                      ->share('recache', [$this, 'getControllerReCacheService']);
            $container->alias(CfVerifyToken::class, 'CfVerifyToken')
                      ->share('CfVerifyToken', [$this, 'getControllerCfVerifyTokenService']);
            $container->share(ReCacheModel::class, [$this, 'getModelReCacheModelService']);
        }
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
            $container->get(AdminTasks::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerCleanCacheService(Container $container): CleanCache
    {
        $controller = new CleanCache(
            $container->get(CacheMaintainer::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerConfigurationsService(Container $container): Configurations
    {
        return new Configurations(
            $container->get(ConfigurationsHtml::class),
            $container->get(PathsInterface::class),
            $container->get(Input::class)
        );
    }

    public function getControllerDeleteBackupsService(Container $container): DeleteBackups
    {
        $controller = new DeleteBackups(
            $container->get(AdminTasks::class),
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
            $container->get(AdminTasks::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerMainService(Container $container): Main
    {
        return (new Main(
            $container->get(MainHtml::class),
            $container->get(Icons::class),
            $container->get(NotificationIcons::class),
            $container->get(Input::class)
        ))->setContainer($container);
    }

    public function getControllerOptimizeImagesService(Container $container): OptimizeImages
    {
        $controller = new OptimizeImages(
            $container->get(OptimizeImageHtml::class),
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
            $container->get(AdminTasks::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerToggleSettingService(Container $container): ToggleSetting
    {
        $controller = new ToggleSetting(
            $container->get(Configure::class),
            $container->get(CacheInterface::class),
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerPageCacheService(Container $container): PageCache
    {
        $controller = new PageCache(
            $container->get(Registry::class),
            $container->get(PageCacheHtml::class),
            $container->get(PageCacheModel::class),
            $container,
            $container->get(Input::class)
        );

        $controller->setLogger($container->get(WordpressNoticeLogger::class));

        return $controller;
    }

    public function getControllerReCacheService(Container $container): ReCache
    {
        return new ReCache(
            $container->get(ReCacheModel::class),
            $container->get(WordpressNoticeLogger::class),
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
            $container->get(CacheMaintainer::class),
            $container->get(Input::class)
        );
    }

    public function getControllerCfVerifyTokenService(Container $container): CfVerifyToken
    {
        return new CfVerifyToken(
            $container->get(ClientInterface::class),
            new CfVerifyJson(),
            $container->get(Input::class)
        );
    }

    public function getModelConfigureService(Container $container): Configure
    {
        $model = new Configure($container->get(CacheInterface::class));
        $model->setState($container->get(Registry::class));
        $model->setContainer($container);

        return $model;
    }

    public function getModelNotificationIconsService(Container $container): NotificationIcons
    {
        return new NotificationIcons(
            $container->get(Registry::class),
            $container->get(Updater::class)
        );
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
        $reCacheModel = new ReCacheModel(
            $container->get(Registry::class),
            $container->get(PathsInterface::class),
            $container->get(CacheMaintainer::class)
        );
        $reCacheModel->setLogger($container->get(LoggerInterface::class));
        $reCacheModel->setContainer($container);

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
        $view = new View($container->get(RendererInterface::class));

        $layout = $container->get(Input::class)->get('tab', 'main') . '.php';
        $view->setLayout($layout);

        return $view;
    }

    public function getViewOptimizeImageHtmlService(Container $container): OptimizeImageHtml
    {
        return (new OptimizeImageHtml(
            $container->get(Registry::class),
            $container->get(RendererInterface::class)
        ))->setLayout('optimizeimages.php');
    }

    public function getViewMainHtmlService(Container $container): MainHtml
    {
        return (new MainHtml(
            $container->get(RendererInterface::class)
        ))->setLayout('main.php');
    }

    public function getViewConfigurationsHtmlService(Container $container): ConfigurationsHtml
    {
        return (new ConfigurationsHtml(
            $container->get(RendererInterface::class),
        ))->setLayout('configurations.php');
    }

    public function getViewPageCacheHtmlService(Container $container): PageCacheHtml
    {
        return (new PageCacheHtml(
            $container->get(RendererInterface::class),
        ))->setLayout('pagecache.php');
    }

    public function getRendererService(Container $container): RendererInterface
    {
        $engine = new PhpRenderer(
            $container->get(PathsInterface::class)->templatePath(),
            [],
            'template.php'
        );

        return new Renderer($engine);
    }
}

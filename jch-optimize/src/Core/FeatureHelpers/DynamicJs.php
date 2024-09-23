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
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\LinkBuilder;
use JchOptimize\Core\Http2Preload;
use Joomla\DI\Container;
use Joomla\Registry\Registry;

use function array_merge;
use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class DynamicJs extends AbstractFeatureHelper
{
    /**
     * @var array $criticalJs Array of javascript files/scripts excluded from the Reduce Unused Js feature
     */
    public static array $criticalJs = [];
    /**
     * @var array $criticalJsDeferred Javascript files excluded from Reduce Unused but were deferred
     */
    public static array $criticalJsDeferred = [];
    /**
     * @var array $aJsDynamicUrls Array of Js Urls to load dynamically for Reduce Unused Js feature
     */
    public static array $aJsDynamicUrls = [];
    /**
     * @var array $dynamicJs Array of files that should be loaded dynamically
     */
    public static array $dynamicJs = [];
    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;
    /**
     * @var LinkBuilder
     */
    private LinkBuilder $linkBuilder;
    /**
     * @var FilesManager
     */
    private FilesManager $filesManager;
    /**
     * @var bool
     */
    private bool $enable;
    /**
     * @var bool  Whether to load the critical JavaScript asynchronously or to defer. If we're excluding modules then it's best to defer
     */
    private bool $loadCriticalJsAsync = true;
    /**
     * @var bool The reverse of the above
     */
    private bool $loadCriticalJsDefer = false;


    public function __construct(
        Container $container,
        Registry $params,
        CacheManager $cacheManager,
        LinkBuilder $linkBuilder,
        FilesManager $filesManager
    ) {
        parent::__construct($container, $params);

        $this->cacheManager = $cacheManager;
        $this->linkBuilder = $linkBuilder;
        $this->filesManager = $filesManager;

        $this->enable = (bool)$this->params->get('pro_reduce_unused_js_enable', '0');
    }

    public function appendCriticalJsToHtml(): void
    {
        if ($this->enable) {
            $criticalJsToCombine = array_merge(self::$criticalJs, self::$criticalJsDeferred);

            if (!empty($criticalJsToCombine)) {
                $this->cacheManager->getCombinedFiles($criticalJsToCombine, $criticalJsId, 'js');
                $criticalJsUrl = $this->linkBuilder->buildUrl($criticalJsId, 'js');

                /** @see http2Preload::add() */
                $this->getContainer()->get(Http2Preload::class)->add($criticalJsUrl, 'js');

                //if files were excluded pei better to defer dynamic files
                if ($this->filesManager->jsFilesExcludedPei) {
                    $this->loadCriticalJsAsync = false;
                    $this->loadCriticalJsDefer = true;
                }

                if(!$this->params->get('pro_defer_criticalJs', '1')) {
                    $this->loadCriticalJsAsync = false;
                    $this->loadCriticalJsDefer = false;
                }

                $criticalJsScript = $this->linkBuilder->getNewJsLink(
                    (string)$criticalJsUrl,
                    $this->loadCriticalJsDefer,
                    $this->loadCriticalJsAsync
                );
                $this->linkBuilder->appendChildToHead($criticalJsScript);
            }
        }
    }

    /**
     * @param array $defers
     *
     * @return void
     */
    public function prepareJsDynamicUrls(array $defers): void
    {
        if (empty(self::$dynamicJs) && empty($defers[0])) {
            return;
        }

        //Let's just go ahead and combine all $dynamicJs
        if (!empty(self::$dynamicJs)) {
            foreach (self::$dynamicJs as $dynamicJsGroup) {
                $this->processDynamicScripts($dynamicJsGroup);
            }
        }

        foreach ($defers as $deferGroup) {
            if ($deferGroup[0]['attributeType'] == 'defer' || $deferGroup[0]['attributeType'] == 'async') {
                $this->processDynamicScripts($deferGroup, true);
            } elseif ($deferGroup[0]['attributeType'] == 'nomodule') {
                $this->cacheManager->getAppendedFiles([], $deferGroup, $dynamicNomoduleId);
                self::$aJsDynamicUrls[] = [
                    'url'      => $this->linkBuilder->buildUrl($dynamicNomoduleId, 'js'),
                    'module'   => false,
                    'nomodule' => true
                ];
            } //modules
            else {
                foreach ($deferGroup as $deferArray) {
                    //handle module files
                    $this->processModules($deferArray, 'url', 'pro_criticalModules');
                    //handle module scripts
                    $this->processModules($deferArray, 'content', 'pro_criticalModulesScripts');
                }
            }
        }

        //We now add any critical JS to the HEAD section of the document
        $this->appendCriticalJsToHtml();
    }

    /**
     * @param array $jsGroup Multidimensional array of JavaScript files to load dynamically
     * @param bool $deferred If we're handling files that were deferred
     *
     * @return void
     */
    private function processDynamicScripts(array $jsGroup, bool $deferred = false): void
    {
        $jsToCombine = [];

        //Filter out critical deferred scripts
        foreach ($jsGroup as $jsArray) {
            if ((!empty($jsArray['url'])
                    && Helper::findExcludes(
                        Helper::getArray($this->params->get('pro_criticalJs', [])),
                        $jsArray['url']
                    ))
                || (!empty($jsArray['content'])
                    && Helper::findExcludes(
                        Helper::getArray($this->params->get('pro_criticalScripts', [])),
                        $jsArray['content']
                    ))) {
                if ($deferred) {
                    self::$criticalJsDeferred[] = $jsArray;
                } else {
                    self::$criticalJs[] = $jsArray;
                }

                continue;
            }

            $jsToCombine[] = $jsArray;
        }

        if (!empty($jsToCombine)) {
            $this->cacheManager->getAppendedFiles([], $jsToCombine, $dynamicJsId);
            self::$aJsDynamicUrls[] = [
                'url'      => $this->linkBuilder->buildUrl($dynamicJsId, 'js'),
                'module'   => false,
                'nomodule' => false
            ];
        }
    }

    /**
     * @param string $type Whether url or content
     * @param string $param Which params to use to search for excludes
     *
     * @return void
     */
    private function processModules(array $moduleArray, string $type, string $param): void
    {
        if (!empty($moduleArray[$type])) {
            if (Helper::findExcludes(Helper::getArray($this->params->get($param, [])), $moduleArray[$type])) {
                $this->linkBuilder->appendChildToHTML($moduleArray['script'], 'body');
                //Best to defer the critical js instead
                $this->loadCriticalJsAsync = false;
                $this->loadCriticalJsDefer = true;
            } else {
                self::$aJsDynamicUrls[] = [
                    $type      => $moduleArray[$type],
                    'module'   => true,
                    'nomodule' => false
                ];
            }
        }
    }
}

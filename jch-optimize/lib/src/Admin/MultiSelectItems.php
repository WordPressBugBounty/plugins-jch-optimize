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
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\CallbackCache;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Html;
use CodeAlfa\Minify\Js;
use JchOptimize\Container\ContainerFactory;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\ElementObject;
use JchOptimize\Core\Html\Elements\Iframe;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\Elements\Input;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Html\Parser;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\HtmlInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SerializableTrait;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use Serializable;

use function array_diff;
use function array_filter;
use function array_merge;
use function array_unique;
use function defined;
use function explode;
use function preg_match;
use function preg_replace;
use function preg_split;
use function trim;
use function ucfirst;

use const JCH_PRO;
use const PREG_SET_ORDER;

defined('_JCH_EXEC') or die('Restricted access');

class MultiSelectItems implements Serializable, ContainerAwareInterface
{
    use SerializableTrait;
    use ContainerAwareTrait;

    protected array $links = [];

    public function __construct(
        private Registry $params,
        private CallbackCache $callbackCache,
        private ExcludesInterface $excludes,
        private ProfilerInterface $profiler
    ) {
    }

    public function prepareStyleValues(string $style): string
    {
        return $this->prepareScriptValues($style);
    }

    public function prepareScriptValues(string $script): string
    {
        return FileUtils::prepareContentForDisplay($script, true, 56);
    }

    public function prepareImagesValues(string $image): string
    {
        return $image;
    }

    public function prepareFolderValues($folder): string
    {
        return FileUtils::prepareFileForDisplay(Utils::uriFor($folder));
    }

    public function prepareFileValues($file): string
    {
        return FileUtils::prepareFileForDisplay(Utils::uriFor($file));
    }

    public function prepareClassValues($class): string
    {
        return FileUtils::prepareContentForDisplay($class, false);
    }

    public function prepareOriginValues($origin): string
    {
        return FileUtils::prepareOriginValue(Utils::uriFor($origin));
    }

    public function getAdminLinks(string $html = '', string $css = '', bool $bCssJsOnly = false): array
    {
        if (empty($this->links)) {
            $aFunction = [$this, 'generateAdminLinks'];
            $aArgs = [$html, $css, $bCssJsOnly];
            $this->links = $this->callbackCache->call($aFunction, $aArgs);
        }

        return $this->links;
    }

    public function generateAdminLinks(string $html, string $css, bool $bCssJsOnly): array
    {
        !JCH_DEBUG ?: $this->profiler->start('GenerateAdminLinks');

        //We need to get a new instance of the container here as we'll be changing the params,
        // and we don't want to mess things up
        $container = ContainerFactory::resetContainer($this->getContainer());

        $params = $container->get('params');
        $params->set('combine_files_enable', '1');
        $params->set('combine_files', '1');
        $params->set('pro_smart_combine', '0');
        $params->set('javascript', '1');
        $params->set('css', '1');
        $params->set('gzip', '0');
        $params->set('css_minify', '0');
        $params->set('js_minify', '0');
        $params->set('html_minify', '0');
        $params->set('defer_js', '0');
        $params->set('debug', '0');
        $params->set('bottom_js', '1');
        $params->set('includeAllExtensions', '1');
        $params->set('excludeCss', []);
        $params->set('excludeJs', []);
        $params->set('excludeAllStyles', []);
        $params->set('excludeAllScripts', []);
        $params->set('excludeJs_peo', []);
        $params->set('excludeJsComponents_peo', []);
        $params->set('excludeScripts_peo', []);
        $params->set('excludeCssComponents', []);
        $params->set('excludeJsComponents', []);
        $params->set('csg_exclude_images', []);
        $params->set('csg_include_images', []);

        $params->set('phpAndExternal', '1');
        $params->set('inlineScripts', '1');
        $params->set('inlineStyle', '1');
        $params->set('replaceImports', '0');
        $params->set('loadAsynchronous', '0');
        $params->set('cookielessdomain_enable', '0');
        $params->set('lazyload_enable', '0');
        $params->set('optimizeCssDelivery_enable', '0');
        $params->set('pro_excludeLazyLoad', []);
        $params->set('pro_excludeLazyLoadFolders', []);
        $params->set('pro_excludeLazyLoadClass', []);
        $params->set('pro_reduce_unused_css', '0');
        $params->set('pro_reduce_unused_js_enable', '0');
        $params->set('pro_reduce_dom', '0');

        try {
            //If we're doing multiselect it's better to fetch the HTML here than send it as an args
            //to prevent different cache keys generating when passed through callback cache
            if ($html == '') {
                /** @var HtmlInterface $oHtml */
                $oHtml = $container->get(HtmlInterface::class);
                $html = $oHtml->getHomePageHtml();
            }
            /** @var HtmlProcessor $oHtmlProcessor */
            $oHtmlProcessor = $container->get(HtmlProcessor::class);
            $oHtmlProcessor->setHtml($html);
            $oHtmlProcessor->processCombineJsCss();

            /** @var FilesManager $oFilesManager */
            $oFilesManager = $container->get(FilesManager::class);
            $aLinks = [
                'css' => $oFilesManager->aCss,
                'js' => $oFilesManager->aJs
            ];

            //Only need css and js links if we're doing smart combine
            if ($bCssJsOnly) {
                return $aLinks;
            }

            if ($css == '' && !empty($aLinks['css'][0])) {
                $oCombiner = $container->get(Combiner::class);

                $resultObj = $oCombiner->combineFiles($aLinks['css'][0]);
                $css = $resultObj->getContents();
            }

            if (JCH_PRO) {
                $aLinks['criticaljs'] = $aLinks['js'];
                $aLinks['modules'] = [];

                /** @var Script $defer */
                foreach ($oFilesManager->deferredScriptStorage as $defer) {
                    if ($defer->getType() == 'module') {
                        if ($defer->getSrc() instanceof UriInterface) {
                            $aLinks['modules'][0][] = new FileInfo($defer);
                        } else {
                            $aLinks['modulesScripts'][0][] = new FileInfo($defer);
                        }
                    } elseif (
                        !$defer->hasAttribute('nomodule')
                        && ($defer->hasAttribute('defer') || $defer->hasAttribute('async'))
                        && ($defer->getSrc()) instanceof UriInterface
                    ) {
                            $aLinks['criticaljs'][0][] = new FileInfo($defer);
                    }
                }
            }
            /** @var Generator $oSpriteGenerator */
            $oSpriteGenerator = $container->get(Generator::class);
            $aLinks['images'] = $oSpriteGenerator->processCssUrls($css, true);

            $oHtmlParser = new Parser();
            $oHtmlParser->addExcludes(['script','noscript','textarea']);

            $oElement = new ElementObject();
            $oElement->setNamesArray(['img', 'iframe', 'input']);
            $oElement->voidElementOrStartTagOnly = true;
            $oElement->addNegAttrCriteriaRegex('(?:data-(?:src|original))');
            $oElement->addNegAttrCriteriaRegex('type!=image');
            $oElement->addPosAttrCriteriaRegex('src|class');
            $oHtmlParser->addElementObject($oElement);

            $aMatches = $oHtmlParser->findMatches($oHtmlProcessor->getBodyHtml(), PREG_SET_ORDER);
            $aLinks['lazyloadclass'] = [];
            $aLinks['lazyload'] = [];

            foreach ($aMatches as $match) {
                $element = HtmlElementBuilder::load($match[0]);


                if ($element instanceof Img || $element instanceof Iframe || $element instanceof Input) {
                    if (JCH_PRO) {
                        $aLinks['lazyloadclass'] = array_merge(
                            $aLinks['lazyloadclass'],
                            LazyLoadExtended::getLazyLoadClassOrId($element)
                        );
                    }

                    if ($element->hasAttribute('src')) {
                        $aLinks['lazyload'][] = $element->getSrc();
                    }
                }
            }
        } catch (Exception\ExceptionInterface $e) {
            $aLinks = [];
        }

        !JCH_DEBUG ?: $this->profiler->stop('GenerateAdminLinks', true);

        return $aLinks;
    }

    public function prepareFieldOptions(
        string $type,
        string $excludeParams,
        string $group = '',
        bool $bIncludeExcludes = true
    ): array {
        if ($type == 'lazyload') {
            $fieldOptions = $this->getLazyLoad($group);
            $group = 'file';
        } elseif ($type == 'images') {
            $group = 'file';
            $aM = explode('_', $excludeParams);
            $fieldOptions = $this->getImages($aM[1]);
        } else {
            $fieldOptions = $this->getOptions($type, $group . 's');
        }

        $options = [];
        $excludes = Helper::getArray($this->params->get($excludeParams, []));

        foreach ($excludes as $exclude) {
            if (is_array($exclude)) {
                foreach ($exclude as $key => $value) {
                    if ($key == 'url' && is_string($value)) {
                        $options[$value] = $this->prepareGroupValues($group, $value);
                    }
                }
            } else {
                $options[$exclude] = $this->prepareGroupValues($group, $exclude);
            }
        }

        //Should we include saved exclude parameters?
        if ($bIncludeExcludes) {
            return array_merge($fieldOptions, $options);
        } else {
            return array_diff($fieldOptions, $options);
        }
    }

    private function prepareGroupValues(string $group, string $value)
    {
        return $this->{'prepare' . ucfirst($group) . 'Values'}($value);
    }

    public function getLazyLoad(string $group): array
    {
        $aLinks = $this->links;

        $aFieldOptions = [];

        if ($group == 'file' || $group == 'folder') {
            if (!empty($aLinks['lazyload'])) {
                foreach ($aLinks['lazyload'] as $imageUri) {
                    if ($group == 'folder') {
                        $regex = '#(?<!/)/[^/\n]++$|(?<=^)[^/.\n]++$#';
                        $i = 0;

                        $imageUrl = FileUtils::prepareFileForDisplay($imageUri, false);
                        $folder = preg_replace($regex, '', $imageUrl);

                        while (preg_match($regex, $folder)) {
                            $aFieldOptions[$folder] = FileUtils::prepareFileForDisplay(Utils::uriFor($folder));

                            $folder = preg_replace($regex, '', $folder);

                            $i++;

                            if ($i == 12) {
                                break;
                            }
                        }
                    } else {
                        $imageUrl = FileUtils::prepareFileForDisplay($imageUri, false);

                        $aFieldOptions[$imageUrl] = FileUtils::prepareFileForDisplay($imageUri);
                    }
                }
            }
        } elseif ($group == 'class') {
            if (!empty($aLinks['lazyloadclass'])) {
                foreach ($aLinks['lazyloadclass'] as $sClasses) {
                    $aClass = preg_split('# #', $sClasses, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($aClass as $sClass) {
                        $aFieldOptions[$sClass] = $sClass;
                    }
                }
            }
        }

        return array_filter($aFieldOptions);
    }

    protected function getImages(string $action = 'exclude'): array
    {
        $aLinks = $this->links;

        $aOptions = [];

        if (!empty($aLinks['images'][$action])) {
            foreach ($aLinks['images'][$action] as $sImage) {
                //$aImage = explode('/', $sImage);
                //$sImage = array_pop($aImage);

                $aOptions = array_merge($aOptions, [
                        FileUtils::prepareUrlValue($sImage) => FileUtils::prepareFileForDisplay($sImage)
                ]);
            }
        }

        return array_unique($aOptions);
    }

    protected function getOptions(string $type, string $group = 'files'): array
    {
        $aLinks = $this->links;

        $aOptions = [];

        if (!empty($aLinks[$type][0])) {
            /** @var FileInfo $fileInfo */
            foreach ($aLinks[$type][0] as $fileInfo) {
                if ($fileInfo->hasUri()) {
                    $uri = $fileInfo->getUri();

                    if ($group == 'files') {
                        $file = FileUtils::prepareUrlValue($uri);
                        $aOptions[$file] = FileUtils::prepareFileForDisplay($uri);
                    } elseif ($group == 'extensions') {
                        $extension = $this->prepareExtensionValues((string)$uri, false);

                        if ($extension === false) {
                            continue;
                        }

                        $aOptions[$extension] = $extension;
                    }
                } elseif (($content = $fileInfo->getContent()) != '') {
                    if ($group == 'scripts') {
                        $script = trim(Js::optimize($content));
                    } elseif ($group == 'styles') {
                        $script = Html::cleanScript($content, 'css');
                        $script = trim(Css::optimize($script));
                    }

                    if (isset($script)) {
                        $aOptions[FileUtils::prepareContentValue($script)]
                            = FileUtils::prepareContentForDisplay($script);
                    }
                }
            }
        }

        return $aOptions;
    }

    public function prepareExtensionValues(string $url, bool $return = true): bool|string
    {
        if ($return) {
            return $url;
        }

        static $host = '';

        $oUri = SystemUri::currentUri();
        $host = $host == '' ? $oUri->getHost() : $host;

        $result = preg_match('#^(?:https?:)?//([^/]+)#', $url, $m1);
        $extension = $m1[1] ?? '';

        if ($result === 0 || $extension == $host) {
            $result2 = preg_match('#' . $this->excludes->extensions() . '([^/]+)#', $url, $m);

            if ($result2 === 0) {
                return false;
            } else {
                $extension = $m[1];
            }
        }

        return $extension;
    }
}

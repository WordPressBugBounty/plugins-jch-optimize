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

use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Html;
use CodeAlfa\Minify\Js;
use JchOptimize\ContainerFactory;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\ElementObject;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\Parser;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Core\SerializableTrait;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Excludes;
use JchOptimize\Platform\Profiler;
use Joomla\Registry\Registry;
use Laminas\Cache\Pattern\CallbackCache;
use Psr\Http\Message\UriInterface;
use Serializable;

use function addslashes;
use function array_diff;
use function array_filter;
use function array_merge;
use function array_unique;
use function defined;
use function explode;
use function htmlspecialchars;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_replace;
use function strlen;
use function substr;
use function trim;
use function ucfirst;

use const JCH_PRO;

defined('_JCH_EXEC') or die('Restricted access');

class MultiSelectItems implements Serializable
{
    use SerializableTrait;

    protected array $links = [];

    /**
     * @var Registry
     */
    private Registry $params;

    /**
     * @var CallbackCache
     */
    private CallbackCache $callbackCache;
    /**
     * @var FileUtils
     */
    private FileUtils $fileUtils;

    /**
     * Constructor
     *
     * @param Registry $params
     * @param CallbackCache $callbackCache
     * @param FileUtils $fileUtils
     */
    public function __construct(Registry $params, CallbackCache $callbackCache, FileUtils $fileUtils)
    {
        $this->params = $params;
        $this->callbackCache = $callbackCache;
        $this->fileUtils = $fileUtils;
    }

    /**
     *
     * @param string $style
     *
     * @return string
     */
    public function prepareStyleValues(string $style): string
    {
        return $this->prepareScriptValues($style);
    }

    /**
     *
     * @param string $script
     *
     * @return string
     */
    public function prepareScriptValues(string $script): string
    {
        if (strlen($script) > 52) {
            $script = substr($script, 0, 52);
            $eps = '...';
            $script = $script . $eps;
        }

        if (strlen($script) > 26) {
            $script = str_replace($script[26], $script[26] . "\n", $script);
        }

        return $script;
    }

    /**
     *
     * @param string $image
     *
     * @return string
     */
    public function prepareImagesValues(string $image): string
    {
        return $image;
    }

    public function prepareFolderValues($folder): string
    {
        return $this->fileUtils->prepareForDisplay(Utils::uriFor($folder));
    }

    public function prepareFileValues($file): string
    {
        return $this->fileUtils->prepareForDisplay(Utils::uriFor($file));
    }

    public function prepareClassValues($class): string
    {
        return $this->fileUtils->prepareForDisplay(null, $class, false);
    }

    /**
     * Returns a multidimensional array of items to populate the multi-select exclude lists in the
     * admin settings section
     *
     * @param string $html HTML before it's optimized by JCH Optimize
     * @param string $css Combined css contents
     * @param bool $bCssJsOnly True if we're only interested in css and js values only as in smart combine
     *
     * @return array
     * @throws \Exception
     */
    public function getAdminLinks(string $html = '', string $css = '', bool $bCssJsOnly = false): array
    {
        if (empty($this->links)) {
            $aFunction = [$this, 'generateAdminLinks'];
            $aArgs = [$html, $css, $bCssJsOnly];
            $this->links = $this->callbackCache->call($aFunction, $aArgs);
        }

        return $this->links;
    }

    /**
     *
     * @param string $html
     * @param string $css
     * @param bool $bCssJsOnly
     *
     * @return array
     */
    public function generateAdminLinks(string $html, string $css, bool $bCssJsOnly): array
    {
        !JCH_DEBUG ?: Profiler::start('GenerateAdminLinks');

        //We need to get a new instance of the container here as we'll be changing the params, and we don't want to mess things up
        $container = ContainerFactory::getNewContainerInstance();

        $params = $container->get('params');
        $params->set('combine_files_enable', '1');
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
                /** @var \JchOptimize\Platform\Html $oHtml */
                $oHtml = $container->get(AbstractHtml::class);
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

                $aResult = $oCombiner->combineFiles($aLinks['css'][0], 'css');
                $css = $aResult['content'];
            }

            if (JCH_PRO) {
                $aLinks['criticaljs'] = $aLinks['js'];
                $aLinks['modules'] = [];

                foreach ($oFilesManager->defers as $deferGroup) {
                    if ($deferGroup[0]['attributeType'] == 'defer' || $deferGroup[0]['attributeType'] == 'async') {
                        foreach ($deferGroup as $defer) {
                            $aLinks['criticaljs'][0][]['url'] = $defer['url'];
                        }
                    }

                    if ($deferGroup[0]['attributeType'] == 'module') {
                        foreach ($deferGroup as $defer) {
                            if (isset($defer['url'])) {
                                $aLinks['modules'][0][]['url'] = $defer['url'];
                            }
                        }
                    }
                }
            }
            /** @var Generator $oSpriteGenerator */
            $oSpriteGenerator = $container->get(Generator::class);
            $aLinks['images'] = $oSpriteGenerator->processCssUrls($css, true);

            $oHtmlParser = new Parser();
            $oHtmlParser->addExclude(Parser::htmlCommentToken());
            $oHtmlParser->addExclude(Parser::htmlElementsToken(['script', 'noscript', 'textarea']));

            $oElement = new ElementObject();
            $oElement->setNamesArray(['img', 'iframe', 'input']);
            $oElement->bSelfClosing = true;
            $oElement->addNegAttrCriteriaRegex('(?:data-(?:src|original))');
            $oElement->setCaptureAttributesArray(['class', 'src']);
            $oHtmlParser->addElementObject($oElement);

            $aMatches = $oHtmlParser->findMatches($oHtmlProcessor->getBodyHtml());

            if (JCH_PRO) {
                $aLinks['lazyloadclass'] = LazyLoadExtended::getLazyLoadClass($aMatches);
            }

            $aLinks['lazyload'] = array_map(function ($a) {
                return Utils::uriFor($a);
            }, $aMatches[7]);
        } catch (Exception\ExceptionInterface $e) {
            $aLinks = [];
        }

        !JCH_DEBUG ?: Profiler::stop('GenerateAdminLinks', true);

        return $aLinks;
    }

    /**
     *
     * @param string $type
     * @param string $excludeParams
     * @param string $group
     * @param bool $bIncludeExcludes
     *
     * @return array
     */
    public function prepareFieldOptions(
        string $type,
        string $excludeParams,
        string $group = '',
        bool   $bIncludeExcludes = true
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

    /**
     *
     * @param string $group
     *
     * @return array
     */
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

                        $imageUrl = $this->fileUtils->prepareForDisplay($imageUri, '', false);
                        $folder = preg_replace($regex, '', $imageUrl);

                        while (preg_match($regex, $folder)) {
                            $aFieldOptions[$folder] = $this->fileUtils->prepareForDisplay(Utils::uriFor($folder));

                            $folder = preg_replace($regex, '', $folder);

                            $i++;

                            if ($i == 12) {
                                break;
                            }
                        }
                    } else {
                        $imageUrl = $this->fileUtils->prepareForDisplay($imageUri, '', false);

                        $aFieldOptions[$imageUrl] = $this->fileUtils->prepareForDisplay($imageUri);
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

    /**
     *
     * @param string $action
     *
     * @return array
     */
    protected function getImages(string $action = 'exclude'): array
    {
        $aLinks = $this->links;

        $aOptions = [];

        if (!empty($aLinks['images'][$action])) {
            foreach ($aLinks['images'][$action] as $sImage) {
                //$aImage = explode('/', $sImage);
                //$sImage = array_pop($aImage);

                $aOptions = array_merge($aOptions, [$sImage => $this->fileUtils->prepareForDisplay($sImage)]);
            }
        }

        return array_unique($aOptions);
    }

    /**
     *
     * @param string $type
     * @param string $group
     *
     * @return array
     */
    protected function getOptions(string $type, string $group = 'files'): array
    {
        $aLinks = $this->links;

        $aOptions = [];

        if (!empty($aLinks[$type][0])) {
            foreach ($aLinks[$type][0] as $aLink) {
                if (isset($aLink['url']) && (string)$aLink['url'] != '') {
                    if ($group == 'files') {
                        $file = $this->fileUtils->prepareForDisplay($aLink['url'], '', false);
                        $aOptions[$file] = $this->fileUtils->prepareForDisplay($aLink['url']);
                    } elseif ($group == 'extensions') {
                        $extension = $this->prepareExtensionValues($aLink['url'], false);

                        if ($extension === false) {
                            continue;
                        }

                        $aOptions[$extension] = $extension;
                    }
                } elseif (isset($aLink['content']) && $aLink['content'] != '') {
                    if ($group == 'scripts') {
                        $script = Html::cleanScript($aLink['content'], 'js');
                        $script = trim(Js::optimize($script));
                    } elseif ($group == 'styles') {
                        $script = Html::cleanScript($aLink['content'], 'css');
                        $script = trim(Css::optimize($script));
                    }

                    if (isset($script)) {
                        if (strlen($script) > 60) {
                            $script = substr($script, 0, 60);
                        }

                        $script = htmlspecialchars($script);

                        $aOptions[addslashes($script)] = $this->prepareScriptValues($script);
                    }
                }
            }
        }

        return $aOptions;
    }

    /**
     *
     * @staticvar string $sUriBase
     * @staticvar string $sUriPath
     *
     * @param string $url
     * @param bool $return
     *
     * @return string|false
     */
    public function prepareExtensionValues(string $url, bool $return = true)
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
            $result2 = preg_match('#' . Excludes::extensions() . '([^/]+)#', $url, $m);

            if ($result2 === 0) {
                return false;
            } else {
                $extension = $m[1];
            }
        }

        return $extension;
    }
}

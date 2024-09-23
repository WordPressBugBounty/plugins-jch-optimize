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

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\Joomla\DI\Container;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Core\Registry;
use JchOptimize\Platform\Excludes;
use JchOptimize\Platform\Profiler;

\defined('_JCH_EXEC') or exit('Restricted access');
class CombineJsCss extends \JchOptimize\Core\Html\Callbacks\AbstractCallback
{
    /**
     * @var array<string, array{
     *    excludes_peo:array{
     *        js:list<array{ url?:string, script?:string, ieo?:string, dontmove?:string }>,
     *        css:string[],
     *        js_script:list<array{ url?:string, script?:string, ieo?:string, dontmove?:string }>,
     *        css_script:string[]
     *    },
     *    critical_js:array{
     *        js:string[],
     *        script:string[]
     *    },
     *    remove:array{
     *        js:string[],
     *        css:string[]
     *    }
     *}>  Array of excludes parameters
     */
    private array $excludes = ['head' => ['excludes_peo' => ['js' => [[]], 'css' => [], 'js_script' => [[]], 'css_script' => []], 'critical_js' => ['js' => [], 'script' => []], 'remove' => ['js' => [], 'css' => []]]];

    /**
     * @var string Section of the HTML being processed
     */
    private string $section = 'head';

    private FilesManager $filesManager;

    private HtmlProcessor $htmlProcessor;

    /**
     * CombineJsCss constructor.
     */
    public function __construct(Container $container, Registry $params, FilesManager $filesManager, HtmlProcessor $htmlProcessor)
    {
        parent::__construct($container, $params);
        $this->filesManager = $filesManager;
        $this->htmlProcessor = $htmlProcessor;
        $this->setupExcludes();
    }

    public function processMatches(array $matches): string
    {
        if ('' === \trim($matches[0])) {
            return $matches[0];
        }
        if (\preg_match('#^<!--#', $matches[0])) {
            return $matches[0];
        }

        try {
            $element = HtmlElementBuilder::load($matches[0]);
        } catch (PregErrorException $e) {
            return $matches[0];
        }
        if ($element instanceof Script && $element->hasAttribute('src')) {
            if (Helper::uriInvalid($element->getSrc())) {
                return $matches[0];
            }
        }
        if ($element instanceof Link && $element->hasAttribute('href')) {
            if (Helper::uriInvalid($element->getHref())) {
                return $matches[0];
            }
        }
        // Remove js files
        if ($element instanceof Script && $element->hasAttribute('src') && Helper::findExcludes(@$this->excludes[$this->section]['remove']['js'], $element->getSrc())) {
            return '';
        }
        // Remove css files
        if ($element instanceof Link && Helper::findExcludes(@$this->excludes[$this->section]['remove']['css'], $element->getHref())) {
            return '';
        }
        if ($element instanceof Script && (!$this->params->get('javascript', '1') || !$this->params->get('combine_files_enable', '1') || $this->htmlProcessor->isAmpPage)) {
            return $matches[0];
        }
        if (($element instanceof Link || $element instanceof Style) && (!$this->params->get('css', '1') || !$this->params->get('combine_files_enable', '1') || $this->htmlProcessor->isAmpPage)) {
            return $matches[0];
        }
        $this->filesManager->setExcludes($this->excludes[$this->section]);

        return $this->filesManager->processFiles($element);
    }

    public function setSection(string $section): void
    {
        $this->section = $section;
    }

    /**
     * Retrieves all exclusion parameters for the Combine Files feature.
     */
    private function setupExcludes()
    {
        JCH_DEBUG ? Profiler::start('SetUpExcludes') : null;
        $aExcludes = [];
        $params = $this->params;
        // These parameters will be excluded while preserving execution order
        $aExJsComp = $this->getExComp($params->get('excludeJsComponents_peo', ''));
        $aExCssComp = $this->getExComp($params->get('excludeCssComponents', ''));
        $aExcludeJs_peo = Helper::getArray($params->get('excludeJs_peo', ''));
        $aExcludeCss_peo = Helper::getArray($params->get('excludeCss', ''));
        $aExcludeScript_peo = Helper::getArray($params->get('excludeScripts_peo', ''));
        $aExcludeStyle_peo = Helper::getArray($params->get('excludeStyles', ''));
        $aExcludeScript_peo = \array_map(function ($script) {
            if (isset($script['script'])) {
                $script['script'] = \stripslashes($script['script']);
            }

            return $script;
        }, $aExcludeScript_peo);
        $aExcludes['excludes_peo']['js'] = \array_merge($aExcludeJs_peo, $aExJsComp, [['url' => '.com/maps/api/js'], ['url' => '.com/jsapi'], ['url' => '.com/uds'], ['url' => 'typekit.net'], ['url' => 'cdn.ampproject.org'], ['url' => 'googleadservices.com/pagead/conversion']], Excludes::head('js'));
        $aExcludes['excludes_peo']['css'] = \array_merge($aExcludeCss_peo, $aExCssComp, Excludes::head('css'));
        $aExcludes['excludes_peo']['js_script'] = $aExcludeScript_peo;
        $aExcludes['excludes_peo']['css_script'] = $aExcludeStyle_peo;
        $aExcludes['critical_js']['js'] = Helper::getArray($params->get('pro_criticalJs', ''));
        $aExcludes['critical_js']['script'] = Helper::getArray($params->get('pro_criticalScripts', ''));
        $aExcludes['remove']['js'] = Helper::getArray($params->get('remove_js', ''));
        $aExcludes['remove']['css'] = Helper::getArray($params->get('remove_css', ''));
        $this->excludes['head'] = $aExcludes;
        if (1 == $this->params->get('bottom_js', '0')) {
            $aExcludes['excludes_peo']['js_script'] = \array_merge($aExcludes['excludes_peo']['js_script'], [['script' => 'var google_conversion'], ['script' => '.write(', 'dontmove' => 'on']], Excludes::body('js', 'script'));
            $aExcludes['excludes_peo']['js'] = \array_merge($aExcludes['excludes_peo']['js'], [['url' => '.com/recaptcha/api']], Excludes::body('js'));
            $this->excludes['body'] = $aExcludes;
        }
        JCH_DEBUG ? Profiler::stop('SetUpExcludes', \true) : null;
    }

    /**
     * Generates regex for excluding components set in plugin params.
     */
    private function getExComp($excludedComParams): array
    {
        $components = Helper::getArray($excludedComParams);
        $excludedComponents = [];
        if (!empty($components)) {
            $excludedComponents = \array_map(function ($value) {
                if (isset($value['url'])) {
                    $value['url'] = \rtrim($value['url'], '/').'/';
                } else {
                    $value = \rtrim($value, '/').'/';
                }

                return $value;
            }, $components);
        }

        return $excludedComponents;
    }
}

<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Preloads;

use _JchOptimizeVendor\V91\Laminas\EventManager\Event;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\ElementObject;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Html\Parser;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\Utils;

use function array_column;
use function str_replace;

use const PREG_SET_ORDER;

class Preconnector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private PreconnectsCollection $preconnects;

    private PreconnectsCollection $prefetches;

    private bool $enable;

    private bool $googleFontsOptimized = false;

    public function __construct(private Registry $params, private HtmlProcessor $htmlProcessor)
    {
        $this->preconnects = new PreconnectsCollection();
        $this->prefetches = new PreconnectsCollection();

        $this->enable = (bool)$this->params->get('pro_preconnect_domains_enable', '0');
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function setGoogleFontsOptimized(bool $googleFontsOptimized = true): void
    {
        $this->googleFontsOptimized = $googleFontsOptimized;
    }
    /**
     * Listener to prepend all external domains preconnect to the HEAD section of the document
     * on the postProcessHtml event
     *
     * @param Event $event
     *
     * @return void
     */
    public function addPreConnectsToHead(Event $event): void
    {
        //If google fonts were optimized then add the fonts domain to preconnects if necessary
        if ($this->googleFontsOptimized) {
            $this->preconnects->attach(
                new Preconnect(Utils::uriFor('https://fonts.gstatic.com'), 'anonymous')
            );
        }

        if ($this->enable) {
            if (JCH_PRO) {
                $preconnectDomains = Helper::getArray($this->params->get('pro_preconnect_domains', []));
                $this->pushDomainsToPreconnects($preconnectDomains);
            }

            $prefetchDomains = Helper::getArray($this->params->get('dns_prefetch_domains', []));
            $this->pushDomainsToPrefetches($prefetchDomains);
        }

        $htmlManager = $event->getTarget();

        if ($htmlManager instanceof HtmlManager) {
            if ($this->prefetches->count() > 0) {
                /** @var DnsPrefetch $prefetch */
                foreach ($this->prefetches as $prefetch) {
                    try {
                        $htmlManager->prependChildToHead($prefetch->render());
                    } catch (PregErrorException $e) {
                    }
                }
            }
            if ($this->preconnects->count() > 0) {
                $this->checkPreconnects();

                /** @var Preconnect $preconnect */
                foreach ($this->preconnects as $preconnect) {
                    try {
                        $htmlManager->prependChildToHead($preconnect->render());
                    } catch (PregErrorException $e) {
                    }
                }
            }
        }
    }

    public function pushDomainsToPreconnects(array $domains): void
    {
        foreach ($domains as $domain) {
            //For backwards compatibility
            if (!isset($domain['url'])) {
                $oldValue = $domain;
                $domain = [];
                $domain['url'] = $oldValue;
            }

            $uri = Utils::uriFor($domain['url']);

            if (Uri::isAbsolute($uri) || Uri::isNetworkPathReference($uri)) {
                if (isset($domain['anonymous'])) {
                    $crossorigin = 'anonymous';
                } elseif (isset($domain['use-credentials'])) {
                    $crossorigin = 'use-credentials';
                } else {
                    $crossorigin = null;
                }

                $this->preconnects->attach(new Preconnect($uri, $crossorigin));
            }
        }
    }

    public function pushDomainsToPrefetches(array $domains): void
    {
        foreach ($domains as $domain) {
            $uri = Utils::uriFor($domain);

            if (Uri::isAbsolute($uri) || Uri::isNetworkPathReference($uri)) {
                $this->prefetches->attach(new DnsPrefetch($uri));
            }
        }
    }

    /**
     * This removes all current preconnects, saving the domains and adding them to the preconnects array to ensure
     * they are loaded properly, and there are no duplication.
     *
     * @return void
     */
    public function checkPreconnects(): void
    {
        try {
            $oGFParser = new Parser();
            $oGFElement = new ElementObject();
            $oGFElement->setNamesArray(['link']);
            $oGFElement->addPosAttrCriteriaRegex('rel==preconnect');
            $oGFElement->addPosAttrCriteriaRegex('href');
            $oGFElement->voidElementOrStartTagOnly = true;
            $oGFParser->addElementObject($oGFElement);

            $headHtml = $this->htmlProcessor->getHeadHtml();
            $aMatches = $oGFParser->findMatches($headHtml, PREG_SET_ORDER);

            if (!empty($aMatches[0])) {
                $existingPreconnects = array_column($aMatches, 0);
                $cleanedHeadHtml = str_replace($existingPreconnects, '', $headHtml);
                $this->htmlProcessor->setHeadHtml($cleanedHeadHtml);

                foreach ($existingPreconnects as $preconnect) {
                    /** @var Link $linkObj */
                    $linkObj = HtmlElementBuilder::load($preconnect);

                    $this->preconnects->attach(
                        new Preconnect(
                            $linkObj->getHref(),
                            $linkObj->getCrossorigin() === false ? null : $linkObj->getCrossorigin()
                        )
                    );
                }
            }
        } catch (PregErrorException $e) {
            $this->logger->error('Failed searching for Gfont preconnect: ' . $e->getMessage());
        }
    }
}

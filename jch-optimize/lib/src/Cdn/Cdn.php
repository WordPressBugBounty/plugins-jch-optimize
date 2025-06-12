<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Cdn;

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use Exception;
use JchOptimize\Core\Exception\RuntimeException;
use JchOptimize\Core\FeatureHelpers\CdnDomains;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Preloads\Preconnector;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use SplObjectStorage;

use function array_merge;
use function array_unique;
use function preg_match;
use function trim;

defined('_JCH_EXEC') or die('Restricted access');

class Cdn implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private ?string $scheme = null;

    protected SplObjectStorage $domains ;

    /**
     * @var SplObjectStorage $domainsClone Used only in selectDomains to ensure the internal counter isn't moved
     *                                     by another function.
     */
    protected SplObjectStorage $domainsClone;

    /** @var array<string, UriInterface> */
    protected array $filePaths = [];

    /** @var string[]|null */
    protected ?array $cdnFileTypes = null;

    private bool $initialized = false;

    private string $startHtaccessLine = '## BEGIN CDN CORS POLICY - JCH OPTIMIZE ##';

    private string $endHtaccessLine = '## END CDN CORS POLICY - JCH OPTIMIZE ##';


    public function __construct(private Registry $params)
    {
        $this->domains = new SplObjectStorage();
    }

    public function enabled(): bool
    {
        return (bool)$this->params->get('cookielessdomain_enable', '0');
    }

    public function getScheme(): string
    {
        if ($this->scheme === null) {
            $this->scheme = match ((string)$this->params->get('cdn_scheme', '0')) {
                '1' => 'http',
                '2' => 'https',
                default => '',
            };
        }

        return $this->scheme;
    }

    /**
     * Returns an array of file types that will be loaded by CDN
     *
     * @return string[]
     * @throws RuntimeException
     */
    public function getCdnFileTypes(): array
    {
        $this->initialize();

        if ($this->cdnFileTypes !== null) {
            return $this->cdnFileTypes;
        }

        throw new RuntimeException('CDN file types not initialized');
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        /** @var string[] $staticFiles1Array */
        $staticFiles1Array = $this->params->get('staticfiles', self::getStaticFiles());
        $this->cdnFileTypes = [];

        if ($this->enabled()) {
            /** @var string $domain1 */
            $domain1 = $this->params->get('cookielessdomain', '');

            if (trim($domain1) != '') {
                /** @var string[] $customExtArray */
                $customExtArray = $this->params->get('pro_customcdnextensions', []);
                $staticFiles1Array = array_merge($staticFiles1Array, $customExtArray);

                $this->domains->attach(new CdnDomain($domain1, $staticFiles1Array, $this->getScheme()));
            }

            if (JCH_PRO) {
                /** @see CdnDomains::addCdnDomains() */
                $this->container->get(CdnDomains::class)->addCdnDomains($this->domains);
            }

            if (JCH_PRO && $this->params->get('pro_preconnect_domains_enable', '0')) {
                /** @var CdnDomain $cdnDomain */
                foreach ($this->domains as $cdnDomain) {
                    $domain = $cdnDomain->getUri()->withPath('')->withQuery('')->withFragment('');
                    /** @see Preconnector::pushDomainsToPrefetches() */
                    $this->container->get(Preconnector::class)->pushDomainsToPrefetches([$domain]);
                }
            }
        }

        if (!empty($this->domains)) {
            /** @var CdnDomain $domain */
            foreach ($this->domains as $domain) {
                $this->cdnFileTypes = array_merge($this->cdnFileTypes, $domain->getFileExtRegexArray());
            }

            $this->cdnFileTypes = array_unique($this->cdnFileTypes);
        }

        $this->domainsClone = clone $this->domains;

        $this->initialized = true;
    }

    public static function getStaticFiles(): array
    {
        return ['css', 'js', 'jpe?g', 'gif', 'png', 'ico', 'bmp', 'pdf', 'webp', 'svg'];
    }

    /**
     * @param UriInterface $uri
     * @param UriInterface|null $origPath
     *
     * @return UriInterface
     */
    public function loadCdnResource(UriInterface $uri, ?UriInterface $origPath = null): UriInterface
    {
        $this->initialize();

        if (empty($origPath)) {
            $origPath = $uri;
        }

        if (!$this->enabled() || $this->domains->count() == 0) {
            return $origPath;
        }

        $paths = $this->getContainer()->get(PathsInterface::class);
        if (!UriComparator::existsLocally($uri, $this, $paths)) {
            return $origPath;
        }

        //If file already loaded on CDN return
        if ($this->isFileOnCdn($uri)) {
            return $origPath;
        }

        //We're now ready to load path on CDN but let's remove query first
        $path = $uri->getPath();
        //If we haven't matched a cdn domain to this file yet then find one.
        if (!isset($this->filePaths[$path])) {
            $this->filePaths[$path] = $this->selectDomain($uri);
        }

        if ((string)$this->filePaths[$path] === '') {
            return $origPath;
        }

        return $this->filePaths[$path];
    }

    public function getCdnDomains(): SplObjectStorage
    {
        $this->initialize();

        return $this->domains;
    }

    public function isFileOnCdn(UriInterface $uri): bool
    {
        /** @var CdnDomain $cdnDomain */
        foreach ($this->getCdnDomains() as $cdnDomain) {
            if ($uri->getHost() === $cdnDomain->getUri()->getHost()) {
                return true;
            }
        }

        return false;
    }

    private function selectDomain(UriInterface $uri, int $depth = 1): UriInterface
    {
        //If no domain is matched to a configured file type then we'll just return the file
        if ($depth > $this->domainsClone->count()) {
            return $uri;
        }

        try {
            $cdnDomain = $this->getCurrentDomain();
        } catch (RuntimeException) {
            return $uri;
        }

        $this->domainsClone->next();

        if (preg_match("#\.{$cdnDomain->getFileExtRegexString()}$#i", $uri->getPath())) {
           //Some CDNs like Cloudinary includes path to the CDN domain to be prepended to the asset
            $uri = $uri->withPath(
                Helper::appendTrailingSlash($cdnDomain->getUri()->getPath())
                . Helper::removeLeadingSlash($uri->getPath())
            );

            return UriResolver::resolve($cdnDomain->getUri(), UriConverter::absToNetworkPathReference($uri));
        } else {
            return $this->selectDomain($uri, $depth + 1);
        }
    }

    private function getCurrentDomain(): CdnDomain
    {
        if ($this->domainsClone->count() == 0) {
            throw new RuntimeException('No CDN domains found');
        }

        if (!$this->domainsClone->valid()) {
            $this->domainsClone->rewind();
        }

        return $this->domainsClone->current();
    }

    public function reset(): void
    {
        $this->initialized = false;
    }
}

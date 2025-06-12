<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Plugin;

use JchOptimize\Core\Registry;
use SimpleXMLElement;
use stdClass;

use function __;
use function add_filter;
use function add_query_arg;
use function headers_sent;
use function home_url;
use function is_wp_error;
use function json_decode;
use function set_url_scheme;
use function simplexml_load_string;
use function sprintf;
use function str_replace;
use function version_compare;
use function wp_get_wp_version;
use function wp_http_supports;
use function wp_json_encode;
use function wp_remote_get;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

class Updater
{
    private ?SimpleXMLElement $updateInfo = null;
    /**
     * @var Registry
     */
    private Registry $params;

    /**
     * Class constructor
     *
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    public function load(): void
    {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'filterTransient'));
    }

    /**
     * Update transient with information for automatic pro update
     *
     *
     * @param   object  $transient
     *
     * @return object
     */
    public function filterTransient(object $transient): object
    {
        $downloadId = (string)$this->params->get('pro_downloadid', '');

        if (empty($downloadId) || ! $this->queryUpdateSite()) {
            return $transient;
        }

        if ($this->updateInfo instanceof SimpleXMLElement) {
            $updateVersion = (string)$this->updateInfo->version;
            $downloadUrl = (string)$this->updateInfo->downloads->downloadurl;
            $downloadUrl = add_query_arg(array('dlid' => $downloadId),
                $downloadUrl);//Check if there's a newer version to the current version installed
            $doUpdate = version_compare($updateVersion, str_replace('pro-', '', JCH_VERSION), '>');
            if ($doUpdate) {//Insert the transient for the new version
                $obj = new stdClass();

                $obj->slug = 'jch-optimize';
                $obj->plugin = 'jch-optimize/jch-optimize.php';
                $obj->new_version = $updateVersion;
                $obj->url = (string)$this->updateInfo->infourl;
                $obj->package = $downloadUrl;

                $transient->response['jch-optimize/jch-optimize.php'] = $obj;

                unset($transient->no_update['jch-optimize/jch-optimize.php']);
            }
        }

        return $transient;
    }

    /**
     * Get update information from our update site
     */
    private function queryUpdateSite(): bool
    {
        $return = false;
        //update site
        $url = 'https://updates.jch-optimize.net/wordpress-pro.xml';

        $response = wp_remote_get($url);

        if (! is_wp_error($response) && 200 == (int)wp_remote_retrieve_response_code($response)) {
            //Should return an xml document containing the update information
            $oXml = simplexml_load_string(wp_remote_retrieve_body($response));

            if ($oXml instanceof SimpleXMLElement) {
                //Get the most recent update site in the document
                $this->updateInfo = $oXml->update;
                $return           = true;
            }
        }

        return $return;
    }

    public function updateAvailable(): bool
    {
        if (JCH_PRO) {
            $this->queryUpdateSite();
            if ($this->updateInfo instanceof SimpleXMLElement) {
                return version_compare(
                    (string)$this->updateInfo->version,
                    str_replace('pro-', '', JCH_VERSION),
                    '>'
                );
            } else {
                return false;
            }
        } else {
            $response = $this->queryWordPressUpdate();
            $newVersion = $response['plugins']['jch-optimize/jch-optimize.php']['new_version'] ?? false;

            if ($newVersion === false) {
                return false;
            }

            return version_compare(
                $newVersion,
                JCH_VERSION,
                '>'
            );
        }
    }

    private function queryWordPressUpdate(): array
    {
        $plugin = get_plugins('jch-optimize/jch-optimize.php');
        $pluginsRequest = [
            'plugins' => [
                'jch-optimize/jch-optimize.php' => $plugin,
            ],
            'active' => ['jch-optimize/jch-optimize.php']
        ];
        $options = [
            'timeout' => 5,
            'body' => [
                'plugins' => wp_json_encode($pluginsRequest),
               // 'translations' => wp_json_encode([]),
              //  'locale' => wp_json_encode([]),
                'all' => wp_json_encode(true)
            ],
            'user-agent' => 'WordPress/' . wp_get_wp_version() . ';' . home_url('/'),
        ];

        $url      = 'http://api.wordpress.org/plugins/update-check/1.1/';
        $http_url = $url;
        $ssl      = wp_http_supports(array( 'ssl' ));

        if ($ssl) {
            $url = set_url_scheme($url, 'https');
        }

        $raw_response = wp_remote_post($url, $options);

        if ($ssl && is_wp_error($raw_response)) {
            $raw_response = wp_remote_post($http_url, $options);
        }

        if (is_wp_error($raw_response) || 200 !== wp_remote_retrieve_response_code($raw_response)) {
            return [];
        }

        return json_decode(wp_remote_retrieve_body($raw_response), true);
    }
}

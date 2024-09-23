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

namespace JchOptimize\Core;

use DateInterval;
use DateTime;
use JchOptimize\ContainerFactory;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;

use function apache_request_headers;
use function array_intersect;
use function array_keys;
use function array_map;
use function defined;
use function explode;
use function function_exists;
use function gzencode;
use function header;
use function is_null;
use function preg_match;
use function strtolower;
use function strtotime;

defined('_JCH_EXEC') or die('Restricted access');

class Output
{
    /**
     * @param   array  $vars
     * @param   bool   $bSend  True to output to browser otherwise return result
     *
     * @return bool|string|string[]|void
     */
    public static function getCombinedFile(array $vars = [], bool $bSend = true)
    {
        $container = ContainerFactory::getContainer();

        /** @var StorageInterface $cache */
        $cache = $container->get(StorageInterface::class);
        /** @var Registry $params */
        $params = $container->get('params');

        $input = new Input();

        if (empty($vars)) {
            $vars = [
                    'f'    => $input->getBase64('f'),
                    'type' => $input->getWord('type'),
                    'gz'   => $input->getWord('gz', 'nz')
            ];
        }

        try {
            //Temporarily set lifetime to 0 and fetch cache
            $lifetime = $cache->getOptions()->getTtl();
            $cache->getOptions()->setTtl(0);

            $results = $cache->getItem($vars['f']);

            $cache->getOptions()->setTtl($lifetime);
        } catch (ExceptionInterface $e) {
            $results = null;
        }

        if (is_null($results)) {
            if ($bSend) {
                header("HTTP/1.0 404 Not Found");

                echo 'File not found';
            }

            return false;
        }

        if ($bSend) {
            $aTimeMFile = self::RFC1123DateAdd($results[0]['filemtime'], '1 year');

            $timeMfile  = $aTimeMFile['filemtime'] . ' GMT';
            $expiryDate = $aTimeMFile['expiry'] . ' GMT';

            $modifiedSinceTime = '';
            $noneMatch         = '';

            if (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();

                if (isset($headers['If-Modified-Since'])) {
                    $modifiedSinceTime = strtotime($headers['If-Modified-Since']);
                }

                if (isset($headers['If-None-Match'])) {
                    $noneMatch = $headers['If-None-Match'];
                }
            }

            if ($modifiedSinceTime == '' && ! is_null($input->server->getString('HTTP_IF_MODIFIED_SINCE'))) {
                $modifiedSinceTime = strtotime($input->server->getString('HTTP_IF_MODIFIED_SINCE'));
            }

            if ($noneMatch == '' && ! is_null($input->server->getString('HTTP_IF_NONE_MATCH'))) {
                $noneMatch = $input->server->getString('HTTP_IF_NONE_MATCH');
            }

            $etag = $results[0]['etag'];

            if ($modifiedSinceTime == strtotime($timeMfile) || trim($noneMatch) == $etag) {
                // Client's cache IS current, so we just respond '304 Not Modified'.
                header('HTTP/1.1 304 Not Modified');
                header('Content-Length: 0');

                return;
            } else {
                header('Last-Modified: ' . $timeMfile);
            }
        }

        $file = $results[0]['contents'];

        //Return file if we're not outputting to browser
        if (! $bSend) {
            return $file;
        }

        if ($vars['type'] == 'css') {
            header('Content-type: text/css');
        } elseif ($vars['type'] == 'js') {
            header('Content-type: text/javascript');
        }

        header('Expires: ' . $expiryDate);
        header('Accept-Ranges: bytes');
        header('Cache-Control: Public');
        header('Vary: Accept-Encoding');
        header('Etag: ' . $etag);

        $gzip = true;

        if (! is_null($input->server->getString('HTTP_USER_AGENT'))) {
            /* Facebook User Agent
             * facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)
             * LinkedIn User Agent
             * LinkedInBot/1.0 (compatible; Mozilla/5.0; Jakarta Commons-HttpClient/3.1 +http://www.linkedin.com)
             */
            $pattern = strtolower('/facebookexternalhit|LinkedInBot/x');

            if (preg_match($pattern, strtolower($input->server->getString('HTTP_USER_AGENT')))) {
                $gzip = false;
            }
        }

        if (isset($vars['gz']) && $vars['gz'] == 'gz' && $gzip) {
            $supported = [
                    'x-gzip'  => 'gz',
                    'gzip'    => 'gz',
                    'deflate' => 'deflate'
            ];

            if (! is_null($input->server->getString('HTTP_ACCEPT_ENCODING'))) {
                $aAccepted = array_map('trim', (array)explode(',', $input->server->getString('HTTP_ACCEPT_ENCODING')));
                $encodings = array_intersect($aAccepted, array_keys($supported));
            } else {
                $encodings = ['gzip'];
            }

            if (! empty($encodings)) {
                foreach ($encodings as $encoding) {
                    if (($supported[$encoding] == 'gz') || ($supported[$encoding] == 'deflate')) {
                        $compressedFile = gzencode(
                            $file,
                            4,
                            ($supported[$encoding] == 'gz') ? FORCE_GZIP : FORCE_DEFLATE
                        );

                        if ($compressedFile === false) {
                            continue;
                        }

                        header('Content-Encoding: ' . $encoding);

                        $file = $compressedFile;

                        break;
                    }
                }
            }
        }

        echo $file;
    }

    /**
     * @param   int     $fileMTime
     * @param   string  $period
     *
     * @return array
     */
    public static function RFC1123DateAdd(int $fileMTime, string $period): array
    {
        $times = [];

        $date = new DateTime();
        $date->setTimestamp($fileMTime);

        $times['filemtime'] = $date->format('D, d M Y H:i:s');

        $date->add(DateInterval::createFromDateString($period));
        $times['expiry'] = $date->format('D, d M Y H:i:s');

        return $times;
    }

}

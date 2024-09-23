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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Core\Exception;
use Joomla\Registry\Registry;
use Psr\Http\Client\ClientInterface;

use function array_merge;
use function defined;
use function extension_loaded;
use function ini_set;
use function is_null;
use function json_decode;
use function json_encode;
use function mime_content_type;
use function preg_replace;
use function strtolower;

defined('_JCH_EXEC') or die('Restricted access');

class ImageUploader
{
    protected array $auth = [];

    protected array $files = [];
    /**
     * @var (Client&ClientInterface)|null
     */
    private $http;

    /**
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(Registry $params, $http)
    {
        if (is_null($http)) {
            throw new Exception\InvalidArgumentException('No http client transporter found', 500);
        }

        $this->http = $http;

        $this->auth = [
                'auth' => [
                        'dlid'   => $params->get('pro_downloadid', ''),
                        'secret' => $params->get('hidden_api_secret', '')
                ]
        ];
    }

    /**
     * @throws \Exception
     *
     * @param ((int[]|string)[]|bool|mixed|string)[] $opts
     *
     * @psalm-param array{files?: list{0?: string,...}|mixed, lossy?: bool, save_metadata?: bool, resize?: array<array<mixed|string>|string, array{width: int, height?: int}>, resize_mode?: 'auto'|'manual', webp?: mixed, url?: ''|mixed} $opts
     */
    public function upload(array $opts = array())
    {
        if (empty($opts['files'][0])) {
            throw new Exception\InvalidArgumentException('File parameter was not provided', 500);
        }

        $files = [];

        foreach ($opts['files'] as $i => $file) {
            $files[] = [
                    'name'     => 'files[' . $i . ']',
                    'contents' => Utils::tryFopen($file, 'r'),
                    'filename' => self::getPostedFileName($file)
            ];
        }

        $this->files = $opts['files'];

        $body = [
                'name'     => 'data',
                'contents' => json_encode(array_merge($this->auth, $opts))
        ];

        $data = array_merge($files, [$body]);

        return self::request($data);
    }

    /**
     * @return false|string
     */
    public static function getMimeType($file)
    {
        return extension_loaded('fileinfo') ? mime_content_type($file) : 'image/' . preg_replace(array(
                        '#\.jpg#',
                        '#^.*?\.(jpeg|png|gif)(?:[?\#]|$)#i'
                ), array(
                        '.jpeg',
                        '\1'
                ), strtolower($file));
    }

    /**
     * @return (mixed|string)[]|string
     *
     * @psalm-return array<mixed|string>|string
     */
    public static function getPostedFileName($file)
    {
        return AdminHelper::contractFileNameLegacy($file);
    }

    /**
     * @param (false|mixed|resource|string)[][] $data
     *
     * @psalm-param list{array{name: 'data', contents: false|string},...} $data
     */
    private function request(array $data)
    {
        ini_set('upload_max_filesize', '50M');
        ini_set('post_max_size', '50M');
        ini_set('max_input_time', '600');
        ini_set('max_execution_time', '600');

        try {
            /** @var Response $response */
            $response = $this->http->post(
                "https://api2.jch-optimize.net/",
                [
                            RequestOptions::MULTIPART => $data
                    ]
            );
        } catch (GuzzleException $e) {
            return new Json(new \Exception('Exception trying to access API with message: ' . $e->getMessage() . ' Files: ' . print_r($this->files, true)));
        }

        if ($response->getStatusCode() !== 200) {
            return new Json(new \Exception('Response returned with status code: ' . $response->getStatusCode(). ' Files: ' .  print_r($this->files, true), 500));
        }

        $body = $response->getBody();
        $body->rewind();

        $contents = json_decode($body->getContents());

        if (is_null($contents)) {
            return new Json(new \Exception('Improper formatted response: ' . $body->getContents()));
        }

        return $contents;
    }
}

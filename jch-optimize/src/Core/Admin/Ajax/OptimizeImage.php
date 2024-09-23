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

namespace JchOptimize\Core\Admin\Ajax;

use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\ContainerFactory;
use JchOptimize\Core\Admin\AbstractHtml;
use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Core\Admin\ImageUploader;
use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;
use Throwable;

use function array_column;
use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function array_values;
use function clearstatcache;
use function count;
use function defined;
use function error_reporting;
use function file_exists;
use function filesize;
use function in_array;
use function ini_get;
use function preg_match;
use function preg_replace_callback;
use function print_r;
use function rtrim;
use function set_time_limit;
use function str_replace;

use const DIRECTORY_SEPARATOR;

defined('_JCH_EXEC') or die('Restricted access');

class OptimizeImage extends Ajax
{
    public static string $backup_folder_name = 'jch_optimize_backup_images';

    /**
     *
     * @param string $src
     * @param string $dest
     *
     * @return bool
     * @deprecated
     */
    public static function copy(string $src, string $dest): bool
    {
        return AdminHelper::copyImage($src, $dest);
    }

    /**
     *
     * @param string $file
     *
     * @depecated
     * @return string|string[]|null
     */
    protected static function incrementBackupFileName(string $file)
    {
        $backup_file = preg_replace_callback(
            '#(?:(_)(\d++))?(\.[^.\s]++)$#',
            function ($m) {
                $m[1] = $m[1] == '' ? '_' : $m[1];
                $m[2] = $m[2] == '' ? 0 : (int)$m[2];

                return $m[1] . (string)++$m[2] . $m[3];
            },
            rtrim($file)
        );

        if (@file_exists($backup_file)) {
            $backup_file = self::incrementBackupFileName($backup_file);
        }

        return $backup_file;
    }

    /**
     *
     * @return Json
     */
    public function run(): Json
    {
        error_reporting(0);

        $root = Paths::rootPath();

        set_time_limit(0);

        //Package of files being sent to be optimized
        $aFilePack = (array)$this->input->get('filepack', [], 'array');
        //Selected subdirectories from the folder tree
        $aSubDirs = (array)$this->input->get('subdirs', [], 'array');
        //Relevant plugin parameters captured by javascript and sent via ajax
        $jsParams = (object)$this->input->get('params', [], 'array');
        //(optimize|getfiles) Whether we're gathering the files to be packaged or
        //we're sending the packages of files to be optimized
        $task = $this->input->getWord('optimize_task', '0');
        $sApiMode = $this->input->getWord('api_mode', 'auto');

        //First task is to get and return an array of image files in the subdirectories that were selected to be optimized
        if ($task == 'getfiles') {
            $files = [];
            $messages = [];

            if ($sApiMode == 'auto') {
                [$files, $messages] = $this->getImageFilesFromUrls();
            } elseif (!empty($aSubDirs)) {
                if (count(array_filter($aSubDirs))) {
                    foreach ($aSubDirs as $sSubDir) {
                        //$subdir = rtrim(Utility::decrypt($subdir), '/\\');
                        $sSubDir = rtrim($sSubDir, '/\\');
                        $files = array_merge($files, $this->getImageFiles($root . $sSubDir, $jsParams->recursive));
                    }
                }
            }

            if ($sApiMode == 'manual' && !empty($files)) {
                //Remove optimized files from the array if option is set
                if ($jsParams->ignore_optimized) {
                    $files = AdminHelper::filterOptimizedFiles($files);
                }

                //Limit number of files optimized at any time to 10,000
                $files = array_slice($files, 0, 10000);

                $files = array_map(function ($v) {
                    return AdminHelper::prepareImageUrl($v);
                }, $files);

                $files = array_values($files);
            }

            $data = [
                'files' => $files,
                'log_path' => Paths::getLogsPath(),
                'messages' => $messages
            ];

            return new Json($data);
        }

        $options = [
            "files" => [],
            "lossy" => (bool)$jsParams->lossy,
            "save_metadata" => (bool)$jsParams->save_metadata,
            "resize" => [],
            "resize_mode" => 'manual',
            "webp" => $jsParams->pro_next_gen_images,
            "url" => ''
        ];


        if ($sApiMode == 'manual') { //Iterate through the packet of files
            foreach ($aFilePack as $file) {
                //Populate the files array with the file names
                //$filepath = rtrim(Utility::decrypt($file['path']), '/\\');
                $filepath = rtrim($file['path'], '/\\');
                $options['files'][] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filepath);

                //If resize dimensions are specified, save them in resize array using file path as index
                if (!empty($file['width']) || !empty($file['height'])) {
                    $filename = AdminHelper::contractFileNameLegacy($filepath);
                    $options['resize'][$filename]['width'] = (int)(!empty($file['width']) ? $file['width'] : 0);
                    $options['resize'][$filename]['height'] = (int)(!empty($file['height']) ? $file['height'] : 0);
                }
            }
        } else {
            $options['files'] = $aFilePack['images'];
            $options['url'] = $aFilePack['url'];
            $options['resize_mode'] = $jsParams->pro_api_resize_mode ? 'auto' : 'manual';
        }

        //Add resize option based on file or folder name


        try {
            $params = $this->getContainer()->get('params');
            $params->set('pro_downloadid', $jsParams->pro_downloadid);
            $params->set('hidden_api_secret', $jsParams->hidden_api_secret);

            /** @var ImageUploader $imageUploader */
            $imageUploader = $this->getContainer()->get(ImageUploader::class);

            $message = '';
            $return = [];
            $data = null;
            //return an array of responses in the data property
            /** @var Json $responses */
            $responses = $imageUploader->upload($options);

            //Check if response is formatted properly
            if (!isset($responses->success)) {
                $this->logger->info('Response not properly formatted: ' . print_r($responses, true));

                throw new \Exception('Unrecognizable response from server:' . $responses, 500);
            }

            //Handle responses that are exceptions (ie, codes 403, 500)
            if (!$responses->success) {
                $this->logger->info($responses->message);

                throw new \Exception($responses->message, $responses->code);
            }

            //Iterate through the array of data
            foreach ($responses->data as $i => $response) {
                $datas = [];

                $original_file = $options['files'][$i];
                $message = $original_file . ': ';

                //Check if file was successfully optimized
                if ($response[0]->success) {
                    //Save backup of file
                    $backup_file = self::getBackupFilename($original_file);
                    $overwriteOriginal = true;

                    if (!@file_exists($backup_file)) {
                        $overwriteOriginal = AdminHelper::copyImage($original_file, $backup_file);
                    }

                    //Copy optimized file over original file only if backup was successful
                    if ($overwriteOriginal && AdminHelper::copyImage($response[0]->data->kraked_url, $original_file)) {
                        $message .= 'Optimized! You saved ' . $response[0]->data->saved_bytes . ' bytes.';
                        AdminHelper::markOptimized($original_file);
                    } else {
                        //If copy failed
                        $message .= 'Could not copy optimized file.';
                        $data = new \Exception($message, 404);
                    }
                } else {
                    //File cannot be optimized further
                    if ($response[0]->code == 304) {
                        AdminHelper::markOptimized($original_file);
                    }

                    //If file wasn't optimized format response accordingly
                    $message .= $response[0]->message;
                    $data = new \Exception($message, $response[0]->code);
                }

                //Format each response
                $data = new Json($data, $message);

                $this->logger->info($data->message);

                //Save each response in the response array
                $datas[] = $data;

                if (isset($response[1])) {
                    $webp_message = '&ensp;└───────────────>  ';
                    $webp_data = null;

                    if ($response[1]->success) {
                        $new_file = Webp::getWebpPath($original_file);

                        if (AdminHelper::copyImage($response[1]->data->webp_url, $new_file)) {
                            $webp_message .= 'Converted to webp! You saved ' . $response[1]->data->webp_savings . ' more bytes';
                            //If this file wasn't backed up before, save a backup now to facilitate restoration
                            $backup_file = self::getBackupFilename($original_file);

                            if (!@file_exists($backup_file)) {
                                AdminHelper::copyImage($original_file, $backup_file);
                            }
                        } else {
                            $webp_message .= 'Could not save the webp version of file!';
                            $webp_data = new \Exception($webp_message);
                        }
                    } else {
                        $webp_message .= $response[1]->message;
                        $webp_data = new \Exception($webp_message, $response[1]->code);
                    }

                    $webp_data = new Json($webp_data, $webp_message);

                    $this->logger->info($webp_data->message);

                    $datas[] = $webp_data;
                }

                $return[] = $datas;
            }
        } catch (\Exception $e) {
            //Save exceptions to datas variable in place of array.
            $return = $e;

            $this->logger->info($e->getMessage());
        }

        clearstatcache();

        //Format Ajax response
        return new Json($return);
    }

    protected function getImageFilesFromUrls(): array
    {
        //multidimensional array of image chunks to send to API to be optimized
        $files = [];

        $container = ContainerFactory::getNewContainerInstance();

        /** @var Registry $params */
        $params = $container->get('params');
        /** @var AbstractHtml $oHtml */
        $oHtml = $container->get(AbstractHtml::class);
        $oHtml->setLogging(true);

        try {
            $options = [
                'base_url' => (string)$params->get('pro_api_base_url', SystemUri::currentBaseFull()),
                'crawl_limit' => (int)$params->get('pro_api_crawl_limit', 15)
            ];

            [$aHtmlArray, $messages] = $oHtml->getCrawledHtmls($options);
        } catch (Throwable $e) {
            $this->logger->info('Couldn\'t get main menu items htmls: ' . $e);
            $aHtmlArray = [];
            $messages = [new Json(new \Exception('Crawl failed: ' . $e->getMessage()))];
        }

        $params->set('combine_files_enable', '1');
        $params->set('pro_smart_combine', '0');
        $params->set('javascript', '0');
        $params->set('css', '1');
        $params->set('css_minify', '0');
        $params->set('excludeCss', []);
        $params->set('excludeCssComponents', []);
        $params->set('replaceImports', '1');
        $params->set('phpAndExternal', '1');
        $params->set('inlineScripts', '1');
        $params->set('lazyload_enable', '0');
        $params->set('cookielessdomain_enable', '0');
        $params->set('optimizeCssDelivery_enable', '0');
        $params->set('csg_enable', '0');

        try {
            foreach ($aHtmlArray as $aHtml) {
                /** @var HtmlProcessor $oHtmlProcessor */
                $oHtmlProcessor = $container->getNewInstance(HtmlProcessor::class);
                $oHtmlProcessor->setHtml($aHtml['html']);
                $aHtmlMatches = $oHtmlProcessor->processImagesForApi();
                $aHtmlImages = [];

                foreach ($aHtmlMatches as $aMatch) {
                    if ($aMatch[1] == 'img') {
                        $aHtmlImages[] = $aMatch[4];

                        if (!empty($aMatch[5])) {
                            $aSrcsetUrls = Helper::extractUrlsFromSrcset($aMatch[7]);

                            foreach ($aSrcsetUrls as $sSrcset) {
                                $aHtmlImages[] = $sSrcset;
                            }
                        }
                    } else {
                        $aHtmlImages[] = $aMatch[6];
                    }
                }

                $oHtmlProcessor->processCombineJsCss();

                $oFilesManager = $container->get(FilesManager::class);
                $aCssLinks = $oFilesManager->aCss;

                $oCombiner = $container->get(Combiner::class);
                $aResult = $oCombiner->combineFiles($aCssLinks[0], 'css');
                $aCssImages = array_unique($aResult['images']);

                $images = array_merge($aHtmlImages, $aCssImages);
                $images = array_unique(array_filter($images));
                //Get the absolute file path of images on filesystem
                $uri = Utils::uriFor($aHtml['url']);
                $images = array_map(function ($a) use ($uri) {
                    $uri = UriResolver::resolve($uri, UriNormalizer::normalize(Utils::uriFor($a)));

                    return UriConverter::uriToFilePath($uri);
                }, $images);

                //Get an array of images that were already collected
                $aImageUrls = array_merge(...array_column($files, 'images'));

                $images = array_filter($images, function ($a) use ($aImageUrls) {
                    return preg_match('#\.(?:jpe?g|png|gif)(?:[?\#]|$)#i', $a)
                        && !in_array($a, $aImageUrls)
                        && @file_exists($a);
                });

                //If option set, remove images already optimized
                if ($params->get('ignore_optimized', '1')) {
                    $images = AdminHelper::filterOptimizedFiles($images);
                }

                if (empty($images)) {
                    continue;
                }

                $images = array_values(array_unique($images));
                //Package images in chunks according to PHP configured limits for uploads
                $imageChunks = $this->packageImages($images);

                foreach ($imageChunks as $imageChunk) {
                    $files[] = [
                        'images' => $imageChunk,
                        'url' => $aHtml['url']
                    ];
                }
            }
        } catch (Exception\ExceptionInterface $e) {
            $this->logger->info('Exception thrown in getImageFilesFromUrl: ' . $e);
        }

        return [$files, $messages];
    }

    /**
     * @deprecated
     */
    public static function getOptimizedFiles()
    {
        return AdminHelper::getOptimizedFiles();
    }

    protected function packageImages(array $aImages): array
    {
        $iMaxUploadFilesize = 0.8 * AdminHelper::stringToBytes(ini_get('upload_max_filesize'));
        $iMaxFileUploads = 0.8 * (int)ini_get('max_file_uploads');
        $aImagePackages = [[]];

        $iTotalFiles = 0;
        $iTotalFileSize = 0;
        $iPackageNumber = 0;

        foreach ($aImages as $sImage) {
            $iFileSize = filesize($sImage);

            if ($iFileSize > $iMaxUploadFilesize) {
                continue;
            }

            $iTotalFileSize += $iFileSize;
            $iTotalFiles++;

            if ($iTotalFileSize > $iMaxUploadFilesize || $iTotalFiles > $iMaxFileUploads || $iTotalFiles > 5) {
                $iTotalFiles = 1;
                $iTotalFileSize = $iFileSize;
                $iPackageNumber++;
            }

            $aImagePackages[$iPackageNumber][] = $sImage;
        }

        return $aImagePackages;
    }

    /**
     * @param string $dir
     * @param bool $recursive
     *
     * @return array
     */
    private function getImageFiles(string $dir, bool $recursive = false): array
    {
        $excludes = [self::$backup_folder_name];

        //Returns an array of full paths of files in the directory (recursively)?
        return Folder::files($dir, '\.(?:gif|jpe?g|png|GIF|JPE?G|PNG)$', $recursive, true, $excludes);
    }

    /**
     *
     * @param string $file
     *
     * @return string
     * @throws \Exception
     */
    protected function getBackupFilename(string $file): string
    {
        $backup_parent_dir = Paths::backupImagesParentDir();

        return $backup_parent_dir . self::$backup_folder_name . '/' . AdminHelper::contractFileName($file);
    }

    /**
     * @param $file
     *
     * @deprecated
     */
    protected static function markOptimized($file): void
    {
        AdminHelper::markOptimized($file);
    }
}

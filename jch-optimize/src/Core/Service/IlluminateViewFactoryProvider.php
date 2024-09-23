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

namespace JchOptimize\Core\Service;

use Illuminate\Contracts\View\Engine;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use JchOptimize\Platform\Paths;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Folder;

use function defined;
use function file_exists;

defined('_JCH_EXEC') or die('Restricted access');

class IlluminateViewFactoryProvider implements ServiceProviderInterface
{

    public function register(Container $container)
    {
        $container->set(
                Factory::class,
                function () {
                    $templateCachePath = Paths::templateCachePath();

                    //Make sure cache path exists
                    if ( ! file_exists($templateCachePath)) {
                        //Create folder including parent folders if they don't exist
                        Folder::create($templateCachePath);
                    }

                    $filesystem = new Filesystem();

                    $resolver = new EngineResolver();

                    $resolver->register(
                            'blade',
                            static function () use ($filesystem, $templateCachePath): Engine {
                                return new CompilerEngine(
                                        new BladeCompiler($filesystem, $templateCachePath)
                                );
                            }
                    );

                    return new Factory(
                            $resolver,
                            new FileViewFinder($filesystem, []),
                            new Dispatcher()
                    );
                }
        );
    }
}

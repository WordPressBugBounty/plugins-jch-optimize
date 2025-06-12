<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html;

use _JchOptimizeVendor\V91\Psr\Container\ContainerInterface;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\Elements\A;
use JchOptimize\Core\Html\Elements\BaseElement;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\LiteYoutube;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Span;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\Elements\Template;

use function class_exists;
use function ucfirst;

/**
 * @method static Link link()
 * @method static Script script()
 * @method static Style style()
 * @method static Img img()
 * @method static Template template()
 * @method static A a()
 * @method static Span span()
 * @method static LiteYoutube liteYoutube()
 */
class HtmlElementBuilder
{
    private static ContainerInterface $container;

    public static array $voidElements = [
        'area',
        'base',
        'br',
        'col',
        'command',
        'embed',
        'hr',
        'img',
        'input',
        'keygen',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr'
    ];

    public static function __callStatic(string $name, array $arguments)
    {
        $class = '\JchOptimize\Core\Html\Elements\\' . ucfirst($name);

        if (class_exists($class)) {
            return new $class(self::$container);
        }

        $element = new BaseElement(self::$container);
        $element->setName($name);

        return $element;
    }

    /**
     * @throws PregErrorException
     */
    public static function load(string $html): HtmlElementInterface
    {
        $builder = new BuildHtmlElement();

        $builder->build($html);

        return $builder->getElement();
    }

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }
}

<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html;

use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\Callbacks\BuildHtmlElement;
use JchOptimize\Core\Html\Elements\BaseElement;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;

/**
 * @method static Link   link()
 * @method static Script script()
 * @method static Style  style()
 * @method static Img    img()
 */
class HtmlElementBuilder
{
    public static array $voidElements = ['area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    public static function __callStatic(string $name, array $arguments)
    {
        $class = '\\JchOptimize\\Core\\Html\\Elements\\'.\ucfirst($name);
        if (\class_exists($class)) {
            return new $class();
        }
        $element = new BaseElement();
        $element->setName($name);

        return $element;
    }

    /**
     * @throws PregErrorException
     */
    public static function load(string $html): HtmlElementInterface
    {
        $parser = new \JchOptimize\Core\Html\Parser();
        $parser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
        $voidElementObj = new \JchOptimize\Core\Html\ElementObject();
        $voidElementObj->setNamesArray(self::$voidElements);
        $voidElementObj->bSelfClosing = \true;
        $voidElementObj->bCaptureAttributes = \true;
        $parser->addElementObject($voidElementObj);
        $elementObj = new \JchOptimize\Core\Html\ElementObject();
        $elementObj->bSelfClosing = null;
        $elementObj->bCaptureAttributes = \true;
        $elementObj->bCaptureContent = \true;
        $elementObj->bParseContentLazily = \false;
        $parser->addElementObject($elementObj);
        $callback = new BuildHtmlElement();
        $parser->processMatchesWithCallback($html, $callback);

        return $callback->getElement();
    }
}

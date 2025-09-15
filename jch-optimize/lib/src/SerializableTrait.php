<?php

/**
 * @package     JchOptimize\Core
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace JchOptimize\Core;

use Exception;
use JchOptimize\Core\FeatureHelpers\Webp;

use function defined;
use function json_encode;

defined('_JCH_EXEC') or die('Restricted access');

trait SerializableTrait
{
    public function __serialize()
    {
        return $this->serializedArray();
    }

    private function serializedArray(): array
    {
        try {
            $webpUsage = $this->getContainer()->get(Webp::class)->getCanIUse();
        } catch (Exception $e) {
            $webpUsage = true;
        }

        return [
            'params' => $this->params->jsonSerialize(),
            'version' => JCH_VERSION,
            'scheme' => SystemUri::currentUri()->getScheme(),
            'authority' => SystemUri::currentUri()->getAuthority(),
            'webpUsage' => $webpUsage
        ];
    }

    public function serialize(): string
    {
        return json_encode($this->serializedArray()) ?: '{}';
    }

    public function __unserialize($data)
    {
        $this->params = $data['params'];
    }

    public function unserialize($data): void
    {
        $this->params = (json_decode($data, true))['params'];
    }
}

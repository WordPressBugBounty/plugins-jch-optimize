<?php

/**
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace JchOptimize\Core;

\defined('_JCH_EXEC') or exit('Restricted access');
trait SerializableTrait
{
    public function __serialize()
    {
        return $this->serializedArray();
    }

    public function __unserialize($data)
    {
        $this->params = $data['params'];
    }

    public function serialize()
    {
        return \json_encode($this->serializedArray());
    }

    public function unserialize($data)
    {
        $this->params = \json_decode($data, \true)['params'];
    }

    private function serializedArray(): array
    {
        return ['params' => $this->params->jsonSerialize(), 'version' => JCH_VERSION, 'scheme' => \JchOptimize\Core\SystemUri::currentUri()->getScheme(), 'authority' => \JchOptimize\Core\SystemUri::currentUri()->getAuthority()];
    }
}

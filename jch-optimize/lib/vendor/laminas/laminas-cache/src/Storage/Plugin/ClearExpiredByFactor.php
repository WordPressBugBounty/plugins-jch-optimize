<?php

namespace _JchOptimizeVendor\Laminas\Cache\Storage\Plugin;

use _JchOptimizeVendor\Laminas\Cache\Storage\ClearExpiredInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\PostEvent;
use _JchOptimizeVendor\Laminas\EventManager\EventManagerInterface;
use function random_int;
class ClearExpiredByFactor extends AbstractPlugin
{
    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $callback = [$this, 'clearExpiredByFactor'];
        $this->listeners[] = $events->attach('setItem.post', $callback, $priority);
        $this->listeners[] = $events->attach('setItems.post', $callback, $priority);
        $this->listeners[] = $events->attach('addItem.post', $callback, $priority);
        $this->listeners[] = $events->attach('addItems.post', $callback, $priority);
    }
    /**
     * Clear expired items by factor after writing new item(s)
     *
     * @return void
     * @phpcs:disable Generic.NamingConventions.ConstructorName.OldStyle
     */
    public function clearExpiredByFactor(PostEvent $event)
    {
        $storage = $event->getStorage();
        if (!$storage instanceof ClearExpiredInterface) {
            return;
        }
        $factor = $this->getOptions()->getClearingFactor();
        if ($factor && random_int(1, $factor) === 1) {
            $storage->clearExpired();
        }
    }
}

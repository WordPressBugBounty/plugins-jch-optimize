<?php

namespace _JchOptimizeVendor\Laminas\Cache\Storage\Plugin;

use _JchOptimizeVendor\Laminas\Cache\Storage\OptimizableInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\PostEvent;
use _JchOptimizeVendor\Laminas\EventManager\EventManagerInterface;
use function random_int;
class OptimizeByFactor extends AbstractPlugin
{
    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $callback = [$this, 'optimizeByFactor'];
        $this->listeners[] = $events->attach('removeItem.post', $callback, $priority);
        $this->listeners[] = $events->attach('removeItems.post', $callback, $priority);
    }
    /**
     * Optimize by factor on a success _RESULT_
     *
     * @return void
     * @phpcs:disable Generic.NamingConventions.ConstructorName.OldStyle
     */
    public function optimizeByFactor(PostEvent $event)
    {
        $storage = $event->getStorage();
        if (!$storage instanceof OptimizableInterface) {
            return;
        }
        $factor = $this->getOptions()->getOptimizingFactor();
        if ($factor && random_int(1, $factor) === 1) {
            $storage->optimize();
        }
    }
}

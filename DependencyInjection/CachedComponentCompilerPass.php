<?php

namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use CTLib\Util\Arr;

/**
 * Compiler pass that registers caached services.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CachedComponentCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $services = $container->findTaggedServiceIds('ctlib.cached_component');

        if (!$services) {
            return;
        }

        foreach ($services as $serviceId => $tagAttributes) {
            $serviceDefinition  = $container->getDefinition($serviceId);
            $serviceClass       = $serviceDefinition->getClass();
            $args               = [
                $serviceId,
                $serviceClass,
                new Reference($serviceId)
            ];
            $definition = $container->getDefinition(
                'cache.manager.' . $tagAttributes[0]['manager']
            );
            $definition->addMethodCall('registerCachedComponent', $args);
        }
    }
}

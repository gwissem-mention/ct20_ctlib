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
class EntityFilterCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $services = $container->findTaggedServiceIds('ctlib.entity_filter_compiler');
        if (!$services) {
            return;
        }

        foreach ($services as $serviceId => $tagAttributes) {
            $definition    = $container->getDefinition($serviceId);
            $args          = [
                $serviceId,
                new Reference($serviceId)
            ];
            $definition->addMethodCall('registerEntityFilterCompiler', $args);
        }
    }
}

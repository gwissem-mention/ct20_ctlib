<?php

namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use CTLib\Util\Arr;

/**
 * Compiler pass that registers entity filter compiler services
 * for ActionLogger service.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class ActionLoggerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('action_log.action_logger')) {
            return;
        }

        $services = $container->findTaggedServiceIds('action_logger.entity_filter_compiler');
        if (!$services) {
            return;
        }

        $definition = $container->getDefinition('action_log.action_logger');

        foreach ($services as $serviceId => $tagAttributes) {
            $args = [
                $serviceId,
                new Reference($serviceId)
            ];
            $definition->addMethodCall('registerEntityFilterCompiler', $args);
        }
    }
}

<?php
namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass that registers garbage collectors.
 * @author Mike Turoff
 */
class GarbageCollectionCompilerPass implements CompilerPassInterface
{

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('garbage_collection_manager') == false) {
            return;
        }

        $services = $container->findTaggedServiceIds('ctlib.garbage_collector');

        if (empty($services)) {
            return;
        }

        $definition = $container->getDefinition('garbage_collection_manager');

        foreach ($services as $serviceId => $tagAttributes) {
            $args = [
                $serviceId,
                new Reference($serviceId)
            ];
            $definition->addMethodCall('addGarbageCollector', $args);
        }
    }

}

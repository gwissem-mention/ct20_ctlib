<?php
namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;



class ProcessLockCompilerPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('process_lock.manager') == false) {
            return;
        }

        $services = $container->findTaggedServiceIds('ctlib.process_lock');

        if (empty($services)) {
            return;
        }

        $definition = $container->getDefinition('process_lock.manager');

        foreach ($services as $serviceId => $tagAttributes) {
            $args = [
                $serviceId,
                new Reference($serviceId)
            ];
            $definition->addMethodCall('addConsumer', $args);
        }
    }
}

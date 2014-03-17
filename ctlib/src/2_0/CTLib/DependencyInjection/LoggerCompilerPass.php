<?php
namespace CTLib\DependencyInjection;


use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface,
    Symfony\Component\DependencyInjection\Reference;

/**
 * Uses custom 'monolog.handler' tag to simplify logger configuration.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class LoggerCompilerPass implements CompilerPassInterface
{
    
    public function process(ContainerBuilder $container)
    {
        if (! $container->hasDefinition('monolog.logger')) {
            return;
        }

        $definition = $container->getDefinition('monolog.logger');
        $services   = $container->findTaggedServiceIds('monolog.handler');

        if (! $services) {
            return;
        }

        // Register tagged handlers with Logger.
        foreach ($services as $serviceId => $tagAttributes) {
            $definition
                ->addMethodCall('pushHandler', array(new Reference($serviceId)));
        }
    }

}
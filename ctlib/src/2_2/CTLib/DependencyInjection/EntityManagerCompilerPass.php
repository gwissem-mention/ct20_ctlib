<?php
namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface,
    Symfony\Component\DependencyInjection\Reference,
    Symfony\Component\DependencyInjection\DefinitionDecorator;


/**
 * Injects QueryMetaMapCache into all EntityManager service definitions.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class EntityManagerCompilerPass implements CompilerPassInterface
{
    
    public function process(ContainerBuilder $container)
    {
        // Inject QueryMetaMapCache service into all EntityManager services.
        $definitions = $container->getDefinitions();

        foreach ($definitions as $serviceId => $definition) {
            if ($definition instanceof DefinitionDecorator
                && $definition->getParent() == 'doctrine.orm.entity_manager.abstract') {

                $definition
                    ->addMethodCall(
                        'setQueryMetaMapCache',
                        array(new Reference('query_meta_map_cache')));
            }
        }
    }

}
<?php
namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;


/**
 * CompilerPass used to register web service request authenticators.
 *
 * @author Mike Turoff
 */
class WebServiceRequestAuthenticatorCompilerPass
    implements CompilerPassInterface
{

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('web_service_request_authentication_verifier')) {
            return;
        }

        $definition = $container->getDefinition('web_service_request_authentication_verifier');
        $services   = $container->findTaggedServiceIds('ctlib.web_service_request_authenticator');

        if (!$services) {
            return;
        }

        foreach ($services as $serviceId => $tagAttributes) {
            $args = [new Reference($serviceId)];
            $definition->addMethodCall('addAuthenticator', $args);
        }
    }

}

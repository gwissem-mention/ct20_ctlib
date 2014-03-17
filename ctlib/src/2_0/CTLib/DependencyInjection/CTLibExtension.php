<?php 
namespace CTLib\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\Config\Definition\Processor,
    Symfony\Component\DependencyInjection\Definition,
    Symfony\Component\DependencyInjection\Reference,
    CTLib\Util\Arr;


class CTLibExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor  = new Processor;
        $config     = $processor
                        ->processConfiguration(new CTLibConfiguration, $configs);
        
        // var_dump($config);die();
        
        $this->loadLoggingServices($config['logging'], $container);
        $this->loadSystemAlertServices($config['system_alerts'], $container);
        $this->loadExceptionListenerServices($config['exception_listener'], $container);
        $this->loadRouteInspectorServices($config['route_inspector'], $container);
        $this->loadORMServices($config['orm'], $container);
        $this->loadSharedCacheServices($config['shared_cache'], $container);
        $this->loadEncryptServices($config['encrypt'], $container);
        $this->loadPushServices($config['push'], $container);
        $this->loadMapServices($config['map_service'], $container);
        $this->loadLocalizationServices($config['localization'], $container);
        $this->loadMutexServices($config['mutex'], $container);
        $this->loadViewServices($config['view'], $container);
    }

    protected function loadLoggingServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $def = new Definition('Monolog\Processor\IntrospectionProcessor');
        $def->addTag('monolog.processor');
        $container->setDefinition('monolog.processors.introspection', $def);

        if ($config['runtime_enabled']) {
            $def = new Definition(
                        'CTLib\Component\Monolog\RuntimeProcessor',
                        array(new Reference('kernel')));
            $def->addTag('monolog.processor');
            $container->setDefinition('monolog.processors.runtime', $def);
        }

        $logDir = $config['dir'] ?: $container->getParameter('kernel.logs_dir');
        $rootDir = $container->getParameter('kernel.root_dir');  

        switch ($config['type']) {
            case 'sqlite':
                $def = new Definition(
                            'CTLib\Component\Monolog\SqliteHandler',
                            array($rootDir, $logDir, $config['level']));
                $def->addTag('monolog.handler');
                $container->setDefinition('monolog.handler.sqlite', $def);
                break;

            case 'tab':
                $def = new Definition(
                            'CTLib\Component\Monolog\TabDelimitedHandler',
                            array($rootDir, $logDir, $config['level']));
                $def->addTag('monolog.handler');
                $container->setDefinition('monolog.handler.tab', $def);
                break;

            default:
                throw new \Exception("Invalid logging type '{$config['type']}'");
        }
    }

    protected function loadSystemAlertServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $args = array(
            new Reference('mailer'),
            new Reference('kernel'),
            $config['from'],
            $config['default_to'],
            $config['threshold_count'],
            $config['threshold_seconds'],
            $config['sleep_seconds'],
            $config['level']
        );

        $def = new Definition('CTLib\Component\Monolog\EmailHandler', $args);
        $def->addTag('monolog.handler');

        if ($config['disable_delivery']) {
            $def->addMethodCall('setDisableDelivery', array(true));
        }

        if ($config['always_send_to']) {
            $def->addMethodCall('setAlwaysSendTo', $config['always_send_to']);
        }

        foreach ($config['rules'] as $ruleConfig) {
            $args = array(
                $ruleConfig['key'],
                $ruleConfig['needle'],
                $ruleConfig['to']
            );
            $def->addMethodCall('addRoutingRule', $args);
        }

        $container->setDefinition('monolog.handler.email', $def);
    }

    protected function loadExceptionListenerServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $args = array(
            $container->getParameter('kernel.environment'),
            $container->getParameter('kernel.debug'),
            new Reference('logger'),
            $config['exec_mode']
        );

        if (is_null($config['exec_mode']) || $config['exec_mode'] == 'std') {
            $args[] = new Reference('session');
        }

        $def = new Definition('CTLib\Listener\ExceptionListener', $args);
        $def->addTag('kernel.event_listener', array('event' => 'kernel.exception'));

        if ($config['redirect']) {
            $def->addMethodCall('setRedirect', array($config['redirect']));
        }

        $container->setDefinition('exception_listener', $def);
    }

    protected function loadRouteInspectorServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $args = array(
            new Reference('router'),
            new Reference('cache'),
            $config['namespace']
        );
        $def = new Definition('CTLib\Component\Routing\RouteInspector', $args);
        $container->setDefinition('route_inspector', $def);
    }

    protected function loadORMServices($config, $container)
    {
        $def = new Definition(
                    'CTLib\Component\Doctrine\ORM\EntityManagerReopener',
                    array(new Reference('service_container')));
        $container->setDefinition('entity_manager_reopener', $def);

        $def = new Definition(
                    'CTLib\Helper\QueryMetaMapCache',
                    array(new Reference('cache')));
        $container->setDefinition('query_meta_map_cache', $def);

        if ($config['entity_listener']['enabled']) {
            $def = new Definition(
                        'CTLib\Listener\EntityListener',
                        array(new Reference('session')));
            
            $attributes = array(
                'event'     => 'prePersist',
                'method'    => 'prePersist'
            );
            $def->addTag('doctrine.event_listener', $attributes);

            $attributes = array(
                'event'     => 'onFlush',
                'method'    => 'onFlush'
            );
            $def->addTag('doctrine.event_listener', $attributes);

            $container->setDefinition('doctrine.entity_listener', $def);
        }
    }

    protected function loadSharedCacheServices($config, $container)
    {
        $def = new Definition(
                    'CTLib\Helper\SharedCacheHelper',
                    array(
                        $config['enabled'],
                        $config['servers'],
                        new Reference('logger')));

        if (isset($config['prefix'])) {
            $def->addMethodCall('setKeyPrefix', array($config['prefix']));
        }

        $container->setDefinition('cache', $def);
    }

    protected function loadEncryptServices($config, $container)
    {
        $def = new Definition(
                    'CTLib\Helper\EncryptHelper',
                    array($config['algorithm'], $config['salt']));
        $container->setDefinition('encrypt', $def);
    }

    protected function loadPushServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $mgrDef = new Definition(
                            'CTLib\Component\Push\PushManager',
                            array(new Reference('logger')));

        if ($config['disable_delivery']) {
            $mgrDef->addMethodCall('setDisableDelivery', array(true));
        }

        $container->setDefinition('push_manager', $mgrDef);

        if (isset($config['platforms']['android'])) {
            $platformConfig = $config['platforms']['android'];
            $serviceId      = 'push.driver.android';

            $def = new Definition(
                        'CTLib\Component\Push\Driver\AndroidPushDriver',
                        array(
                            $platformConfig['service_url'],
                            $platformConfig['service_auth'],
                            new Reference('logger')));
            $container->setDefinition($serviceId, $def);

            $args = array('ANDRD', new Reference($serviceId));
            $mgrDef->addMethodCall('registerDriver', $args);
        }

        if (isset($config['platforms']['blackberry'])) {
            $platformConfig = $config['platforms']['blackberry'];
            $serviceId      = 'push.driver.blackberry';

            $def = new Definition(
                        'CTLib\Component\Push\Driver\BlackBerryPushDriver',
                        array(
                            $platformConfig['service_url'],
                            $platformConfig['service_auth'],
                            $platformConfig['app_id'],
                            $platformConfig['ttl_seconds'],
                            new Reference('logger')));
            $container->setDefinition($serviceId, $def);

            $args = array('BB', new Reference($serviceId));
            $mgrDef->addMethodCall('registerDriver', $args);
        }

        if (isset($config['platforms']['ios'])) {
            $platformConfig = $config['platforms']['ios'];
            $serviceId      = 'push.driver.ios';

            // iOS requires a more complicated process to build its service
            // definition. Pass off to helper.
            $ext = new IOSPushDriverExtension;
            $def = $ext
                    ->buildDefinition(
                        $platformConfig['prod_service_url'],
                        $platformConfig['dev_service_url'],
                        $platformConfig['cert_dir'],
                        $platformConfig['cert_pass']);
            $container->setDefinition($serviceId, $def);

            $args = array('IOS', new Reference($serviceId));
            $mgrDef->addMethodCall('registerDriver', $args);
        }


    }

    protected function loadMapServices($config, $container)
    {
        if (! $config['enabled']) {
            return;
        }

        $mgrDef = new Definition(
                        'CTLib\MapService\MapProviderManager',
                        array($config['country'], new Reference('logger')));
        $container->setDefinition('map_service', $mgrDef);

        foreach ($config['providers'] as $providerConfig) {
            $args = array(
                $providerConfig['class'],
                $providerConfig['countries'],
                $providerConfig['allowedQualityCodes']
            );
            $mgrDef->addMethodCall('registerProvider', $args);
        }
    }

    protected function loadLocalizationServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $args = array(
            new Reference('cache'),
            new Reference('translator'),
            new Reference('service_container'),
            new Reference('session')
        );
        $def = new Definition('CTLib\Helper\LocalizationHelper', $args);
        $container->setDefinition('localizer', $def);
    }

    protected function loadMutexServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        if ($config['dir']) {
            $dir = $config['dir'];
        } else {
            $dir = $container->getParameter('kernel.root_dir') . '/mutex';
        }

        $def = new Definition('CTLib\Util\Mutex', array($dir));
        $container->setDefinition('mutex', $def);
    }

    protected function loadViewServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        if (! $container->hasDefinition('route_inspector')) {
            throw new \Exception("Must enable 'route_inspector' to enable view services");
        }


        $def = new Definition(
                    'CTLib\Helper\AssetHelper',
                    array($container->getParameter('kernel.environment')));

        foreach ($config['asset_dirs'] as $dir) {
            $def->addMethodCall('addDirectory', array($dir['name'], $dir['path']));
        }

        $container->setDefinition('asset', $def);


        $args = array(
            new Reference('translator'),
            new Reference('route_inspector')
        );
        $def = new Definition('CTLib\Helper\JavascriptHelper', $args);
        $container->setDefinition('js', $def);


        if ($config['use_lazy_loader']) {
            $def = new Definition(
                        'CTLib\Listener\TwigLazyLoadListener',
                        array(new Reference('asset')));
            $def->addTag('kernel.event_listener', array('event' => 'kernel.response'));
            $container->setDefinition('twig.lazyload.listener', $def);
        }


        $def = new Definition(
                    'CTLib\Twig\Extension\BaseExtension',
                    array(new Reference('service_container')));
        $def->addTag('twig.extension');
        $container->setDefinition('twig.extension.base', $def);


        $def = new Definition(
                    'CTLib\Twig\Extension\AssetExtension',
                    array(new Reference('asset')));
        $def->addTag('twig.extension');
        $container->setDefinition('twig.extension.asset', $def);


        $args = array(new Reference('js'));

        if ($config['use_lazy_loader']) {
            $args[] = new Reference('twig.lazyload.listener');
        }
        $def = new Definition('CTLib\Twig\Extension\JavascriptExtension', $args);
        $def->addTag('twig.extension');
        $container->setDefinition('twig.extension.js', $def);

    
        if ($container->hasDefinition('localizer')) {
            $def = new Definition(
                        'CTLib\Twig\Extension\LocalizerExtension',
                        array(new Reference('localizer')));
            $def->addTag('twig.extension');
            $container->setDefinition('twig.extension.localizer', $def);
        }


        if ($config['use_dynapart']) {
            $def = new Definition(
                        'CTLib\Twig\Extension\DynaPartExtension',
                        array(new Reference('service_container')));
            $def->addTag('twig.extension');
            $container->setDefinition('twig.extension.dynapart', $def);   
        }





    }


}
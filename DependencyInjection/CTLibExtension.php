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

        $this->loadCacheManagerServices($config['cache'], $container);
        $this->loadSimpleCacheServices($config['simple_cache'], $container);
        $this->loadEntityFilterCacheServices($config['entity_filter_cache'], $container);
        $this->loadProcessLockServices($config['process_lock'], $container);
        $this->loadLoggingServices($config['logging'], $container);
        $this->loadSystemAlertServices($config['system_alerts'], $container);
        $this->loadXhrExceptionListenerServices($config['xhr_exception_listener'], $container);
        $this->loadRedirectExceptionListenerServices($config['redirect_exception_listener'], $container);
        $this->loadRouteInspectorServices($config['route_inspector'], $container);
        $this->loadORMServices($config['orm'], $container);
        $this->loadSharedCacheServices($config['shared_cache'], $container);
        $this->loadEncryptServices($config['encrypt'], $container);
        $this->loadPushServices($config['push'], $container);
        $this->loadMapServices($config['map_service'], $container);
        $this->loadLocalizationServices($config['localization'], $container);
        $this->loadMutexServices($config['mutex'], $container);
        $this->loadUrlsServices($config['urls'], $container);
        $this->loadViewServices($config['view'], $container);
        $this->loadCTAPIServices($config['ct_api'], $container);
        $this->loadHtmlToPdfServices($config['html_to_pdf'], $container);
        $this->loadActionLogServices($config['action_log'], $container);
        $this->loadFilteredObjectIndexServices($config['filtered_object_index'], $container);
        $this->loadConsoleServices([], $container);
        $this->loadWebServiceRequestAuthenticationServices($config['web_service_authentication'], $container);
        $this->loadGarbageCollectionServices([], $container);
    }

    protected function loadCacheManagerServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        foreach ($config['managers'] as $manager) {
            $def = new Definition('CTLib\Component\Cache\CachedComponentManager',[]);
            $container->setDefinition("cache.manager.{$manager}", $def);
        }
    }

    protected function loadSimpleCacheServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        $args = [
            $config['namespace'],
            new Reference($config['redis_client'])
        ];

        $def = new Definition(
            'CTLib\Component\Cache\SimpleCache',
            $args
        );
        $container->setDefinition("simple_cache", $def);
    }

    protected function loadEntityFilterCacheServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        $serviceClass = 'CTLib\Component\Cache\EntityFilterCache';

        foreach ($config['entities'] as $entityName => $entityConfig) {
            $args = [
                $entityConfig['namespace'],
                new Reference($entityConfig['redis_client']),
                $entityConfig['ttl']
            ];
            $def = new Definition($serviceClass, $args);

            $serviceId = "entity_filter_cache.{$entityName}";
            $container->setDefinition($serviceId, $def);
        }
    }

    protected function loadProcessLockServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $args = [
            new Reference($config['redis_client']),
            $config['namespace']
        ];
        $def = new Definition('CTLib\Component\ProcessLock\ProcessLock', $args);
        $container->setDefinition('process_lock', $def);
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

        $def = new Definition(
                    'CTLib\Component\Monolog\SanitizeProcessor',
                    array(new Reference('kernel')));
        $def->addTag('monolog.processor');
        $container->setDefinition('monolog.processors.sanitizer', $def);

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
            $def->addMethodCall('setDisableDelivery', [true]);
        }

        if ($config['always_send_to']) {
            $def->addMethodCall('setAlwaysSendTo', [$config['always_send_to']]);
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

    protected function loadXhrExceptionListenerServices($config, $container)
    {
        if (!$config['enabled']) { return; }

        $args = [
            $container->getParameter('kernel.debug'),
            new Reference('logger')
        ];

        $def = new Definition('CTLib\Listener\XhrExceptionListener', $args);
        $def->addTag('kernel.event_listener', ['event' => 'kernel.exception']);

        if (isset($config['invalidate_session'])) {
            $def
            ->addMethodCall(
                'setInvalidateSession',
                [$config['invalidate_session']]
            );
        }

        $container->setDefinition('exception_listener.xhr', $def);
    }

    protected function loadRedirectExceptionListenerServices($config, $container)
    {
        if (!$config['enabled']) { return; }

        $args = [
            $config['redirect_to'],
            $container->getParameter('kernel.debug'),
            new Reference('logger')
        ];

        $def = new Definition('CTLib\Listener\RedirectExceptionListener', $args);
        $def->addTag('kernel.event_listener', ['event' => 'kernel.exception']);

        if (isset($config['invalidate_session'])) {
            $def
            ->addMethodCall(
                'setInvalidateSession',
                [$config['invalidate_session']]
            );
        }

        $container->setDefinition('exception_listener.redirect', $def);
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
        if (! $config['enabled']) {
            $config['enabled'] = false;
            $config['servers'] = [];
        }

        $def = new Definition(
                    'CTLib\Component\Cache\SharedCache',
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
        if (! $config['enabled']) { return; }

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

            $args = array('BB10', new Reference($serviceId));
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
            array($config['country'], new Reference('logger'), new Reference('localizer')));

        $container->setDefinition('map_service', $mgrDef);
        foreach ($config['providers'] as $providerId => $providerConfig) {
                $args = array(
                $providerId,
                $providerConfig['class'],
                $providerConfig['javascript_url'],
                $providerConfig['javascript_key'],
                $providerConfig['webservice_url'],
                $providerConfig['webservice_key']
                );

            $mgrDef->addMethodCall('registerProvider', $args);
        }

        foreach ($config['geocoders'] as $country => $geocoderConfigs) {
            foreach ($geocoderConfigs as $geoConfig) {
                $args = array(
                    $country,
                    $geoConfig['provider'],
                    $geoConfig['tokens'],
                    $geoConfig['allowedQualityCodes'],
                    $geoConfig['batchSize']
                    );
                $mgrDef->addMethodCall('registerGeocoder', $args);
            }
        }

        foreach ($config['reverseGeocoders'] as
            $country => $reverseGeocoderConfigs) {
            foreach ($reverseGeocoderConfigs as $reverseGeoConfig) {
                $args = array(
                    $country,
                    $reverseGeoConfig['provider']
                    );
                $mgrDef->addMethodCall('registerReverseGeocoder', $args);
            }
        }

        foreach ($config['routers'] as
            $country => $routerConfig) {
            $args = array(
                $country,
                $routerConfig['provider']
                );
            $mgrDef->addMethodCall('registerRouter', $args);
        }

        foreach ($config['timeZoners'] as
                 $country => $routerConfig) {
            $args = [
                $country,
                $routerConfig['provider']
            ];
            $mgrDef->addMethodCall('registerTimeZoner', $args);
        }

        foreach ($config['javascript_apis'] as
            $country => $apiConfig) {
            $args = array(
                $country,
                $apiConfig['provider']
                );
            $mgrDef->addMethodCall('registerAPI', $args);
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

    protected function loadUrlsServices($config, $container)
    {
        $def = new Definition('CTLib\Helper\UrlHelper');
        $container->setDefinition('url', $def);

        foreach ($config as $namespace => $url) {
            $args = [$namespace, $url['host'], $url['asset_path']];
            $def->addMethodCall('addUrl', $args);
        }
    }

    protected function loadViewServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        if (! $container->hasDefinition('route_inspector')) {
            throw new \Exception("Must enable 'route_inspector' to enable view services");
        }

        $args = array(
            new Reference('translator'),
            new Reference('route_inspector')
        );
        $def = new Definition('CTLib\Helper\JavascriptHelper', $args);
        $container->setDefinition('js', $def);

        if (array_key_exists('js', $config)) {
            if ($config['js']['routes']) {
                $def->addMethodCall('addRoute', $config['js']['routes']);
            }
            if ($config['js']['translations']) {
                $def->addMethodCall('addTranslation', $config['js']['translations']);
            }

            if ($config['js']['permissions']) {
                if (isset($config['js']['permissions']['source'])) {
                    $def
                        ->addMethodCall(
                            'setPermissionSource',
                            array(
                                array(
                                    new Reference($config['js']['permissions']['source']),
                                    $config['js']['permissions']['method']
                                )
                            )
                        );
                }
            }
        }

        $args = [
            new Reference('url'),
            $container->getParameter('kernel.environment')
        ];
        $def = new Definition('CTLib\Twig\Extension\AssetExtension', $args);
        $def->addTag('twig.extension');
        $container->setDefinition('twig.extension.asset', $def);

        if ($config['use_lazy_loader']) {
            $def = new Definition(
                        'CTLib\Listener\TwigLazyLoadListener',
                        array(new Reference('twig.extension.asset')));
            $def->addTag('kernel.event_listener', array('event' => 'kernel.response'));
            $container->setDefinition('twig.lazyload.listener', $def);
        }


        $def = new Definition(
                    'CTLib\Twig\Extension\BaseExtension',
                    array(new Reference('service_container')));
        $def->addTag('twig.extension');
        $container->setDefinition('twig.extension.base', $def);

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

    protected function loadCTAPIServices($config, $container)
    {
        if (! $config['enabled']) { return; }

        $args = [
            $config['url'],
            new Reference('logger')
        ];
        $def = new Definition('CTLib\Component\CtApi\CtApiCaller', $args);
        $container->setDefinition('ct_api.caller', $def);

        foreach ($config['authenticators'] as $ctApiAuthenticatorName => $ctApiAuthenticator) {
            $args = [$ctApiAuthenticatorName, new Reference($ctApiAuthenticator)];
            $def->addMethodCall('addAuthenticator', $args);
        }
    }

    protected function loadHtmlToPdfServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        $wkhtmltopdfBinPath = $config['wkhtmltopdf_path'];
        $args = [$wkhtmltopdfBinPath];
        $def = new Definition('CTLib\Component\Pdf\HtmlToPdf', $args);
        $container->setDefinition('htmltopdf', $def);
    }

    protected function loadActionLogServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        $args = [
            new Reference($config['entity_manager']),
            new Reference('ct_api.caller'),
            new Reference('kernel'),
            new Reference('logger'),
            $config['source']
        ];
        $def = new Definition('CTLib\Component\ActionLog\ActionLogger', $args);
        $container->setDefinition('action_log.action_logger', $def);
        $container->setAlias('action_logger', 'action_log.action_logger');

        $args = [
            new Reference($config['entity_manager']),
            new Reference('ct_api.caller')
        ];
        $def = new Definition('CTLib\Component\ActionLog\ActionLogReader', $args);
        $container->setDefinition('action_log.action_log_reader', $def);
        $container->setAlias('action_log_reader', 'action_log.action_log_reader');
    }

    protected function loadFilteredObjectIndexServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        $groupClass = 'CTLib\Component\FilteredObjectIndex\FilteredObjectIndexGroup';
        $loggerReference = new Reference('logger');

        foreach ($config['groups'] as $groupName => $groupConfig) {
            $args = [
                $groupConfig['key_namespace'],
                new Reference($groupConfig['redis_client']),
                $loggerReference
            ];
            $def = new Definition($groupClass, $args);

            foreach ($groupConfig['indexes'] as $index) {
                $def->addMethodCall('addIndex', [$index]);
            }

            $serviceId = "filtered_object_index_group.{$groupName}";
            $container->setDefinition($serviceId, $def);
        }
    }

    protected function loadConsoleServices($config, $container)
    {
        $class = 'CTLib\Component\Console\SymfonyCommandExecutorFactory';
        $args = [
            $container->getParameter('kernel.root_dir'),
            new Reference('logger')
        ];

        $def = new Definition($class, $args);
        $container->setDefinition('symfony_command_executor_factory', $def);
    }

    protected function loadWebServiceRequestAuthenticationServices($config, $container)
    {
        if (!$config['enabled']) {
            return;
        }

        $serviceId = 'web_service_request_authentication_verifier';
        $class = 'CTLib\Component\Security\WebService\WebServiceRequestAuthenticationVerifier';
        $args = [
            new Reference('logger')
        ];

        $def = new Definition($class, $args);

        $tagAttributes = ['event' => 'kernel.request'];
        $def->addTag('kernel.event_listener', $tagAttributes);

        $container->setDefinition($serviceId, $def);
    }

    protected function loadGarbageCollectionServices($config, $container)
    {
        $serviceId = "garbage_collection_manager";
        $class = "CTLib\Component\GarbageCollection\GarbageCollectionManager";

        $def = new Definition($class);
        $container->setDefinition($serviceId, $def);
    }
}

<?php 
namespace CTLib\DependencyInjection;

use \Symfony\Component\HttpKernel\DependencyInjection\Extension,
    \Symfony\Component\DependencyInjection\ContainerBuilder,
    CTLib\Util\Arr;

class CTLibExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadMapServiceConfig($configs, $container);
        $this->loadSharedCacheConfig($configs, $container);
        $this->loadPushDriverConfig($configs, $container);
        $this->loadBusinessValidatorConfig($configs, $container);
    }

    protected function loadMapServiceConfig(array $configs,
        ContainerBuilder $container)
    {
        foreach ($configs AS $config) {
            $providers = Arr::findByKeyChain($config, "map_service.providers");
            if ($providers) {
                $container->setParameter(
                    'ctlib.map_service.providers',
                    $providers
                );        
            }
        }
    }

    protected function loadSharedCacheConfig(array $configs,
        ContainerBuilder $container)
    {
        $sharedCacheEnabled = false;
        $sharedCacheServers = array();
        
        // Use latest set cache config settings.
        foreach ($configs AS $config) {
            if (isset($config['shared_cache'])) {
                if (isset($config['shared_cache']['enabled'])) {
                    $sharedCacheEnabled = $config['shared_cache']['enabled'];
                }
                if (isset($config['shared_cache']['servers'])) {
                    $sharedCacheServers = $config['shared_cache']['servers'];
                }
                
            }
        }

        $container->setParameter(
            'ctlib.shared_cache.enabled',
            $sharedCacheEnabled
        );
        $container->setParameter(
            'ctlib.shared_cache.servers',
            $sharedCacheServers
        );
    }

    protected function loadPushDriverConfig(array $configs,
        ContainerBuilder $container)
    {
        // Each driver may have different parameters injected.
        // We'll convert push.drivers.{driverName}.* into associative array.
        foreach ($configs as $config) {
            $drivers = Arr::findByKeyChain($config, 'push.drivers');
            if (! $drivers) { continue; }

            foreach ($drivers as $driver => $driverConfig) {
                $container->setParameter("push.driver.{$driver}", $driverConfig);
            }    
        }
    }
    
    protected function loadBusinessValidatorConfig(array $configs, 
    	ContainerBuilder $container)
    {
        foreach ($configs AS $config) {
            $providers = Arr::findByKeyChain($config, "business_validate.providers");
            if ($providers) {
                $container->setParameter(
                    'ctlib.business_validate.providers',
                    $providers
                );        
            }
        }    
    }
}
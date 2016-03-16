<?php
namespace CTLib\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface,
    Symfony\Component\Config\Definition\Builder\TreeBuilder;


/**
 * Enforces semantic configuration for CTLib bundle.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class CTLibConfiguration implements ConfigurationInterface
{
    
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder;
        $root = $tb->root('ct_lib');
            
        $root
            ->children()
                ->append($this->addLoggingNode())
                ->append($this->addSystemAlertsNode())
                ->append($this->addExceptionListenerNode())
                ->append($this->addRouteInspectorNode())
                ->append($this->addOrmNode())
                ->append($this->addSharedCacheNode())
                ->append($this->addEncryptNode())
                ->append($this->addMapServiceNode())
                ->append($this->addLocalizationNode())
                ->append($this->addPushNode())
                ->append($this->addMutexNode())
                ->append($this->addUrlsNode())
                ->append($this->addViewNode())
                ->append($this->addCTAPINode()) 
                ->append($this->addHtmlToPdfNode())               
            ->end();

        return $tb;
    }

    protected function addLoggingNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('logging');

        $node
            ->canBeEnabled()
            ->children()
                ->enumNode('type')
                    ->values(array('sqlite','tab'))
                    ->isRequired()
                ->end()
                ->enumNode('level')
                    ->values(array('debug','info','warning','error','alert'))
                    ->defaultValue('debug')
                ->end()
                ->scalarNode('dir')
                    ->defaultNull()
                ->end()
                ->booleanNode('runtime_enabled')
                    ->defaultFalse()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addSystemAlertsNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('system_alerts');

        $node
            ->canBeEnabled()
            ->children()
                ->booleanNode('disable_delivery')
                    ->defaultFalse()
                ->end()
                ->scalarNode('from')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('default_to')
                    ->performNoDeepMerging()
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('always_send_to')
                    ->performNoDeepMerging()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('level')
                    ->defaultValue('error')
                ->end()
                ->integerNode('threshold_count')
                    ->min(1)
                    ->defaultValue(5)
                ->end()
                ->integerNode('threshold_seconds')
                    ->min(1)
                    ->defaultValue(120)
                ->end()
                ->integerNode('sleep_seconds')
                    ->min(1)
                    ->defaultValue(600)
                ->end()
                ->arrayNode('rules')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('key')
                                ->defaultValue('message')
                            ->end()
                            ->scalarNode('needle')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('to')
                                ->isRequired()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

            ->end()
        ->end();

        return $node;
    }

    protected function addExceptionListenerNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('exception_listener');

        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('exec_mode')
                    ->defaultNull()
                ->end()
                ->scalarNode('redirect')
                    ->defaultNull()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addRouteInspectorNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('route_inspector');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('namespace')
                    ->isRequired()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addOrmNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('orm');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('entity_listener')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addSharedCacheNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('shared_cache');

        $node
            ->canBeEnabled()
            ->children()
                ->arrayNode('servers')
                    ->performNoDeepMerging()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('prefix')
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addEncryptNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('encrypt');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('algorithm')
                    ->defaultValue('sha256')
                ->end()
                ->scalarNode('salt')
                    ->isRequired()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addPushNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('push');

        $node
            ->canBeEnabled()
            ->children()
                ->booleanNode('disable_delivery')
                    ->defaultFalse()
                ->end()
                ->arrayNode('platforms')
                    ->children()

                        ->arrayNode('android')
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('service_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('service_auth')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('blackberry')
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('service_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('service_auth')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('app_id')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->integerNode('ttl_seconds')
                                    ->min(1)
                                    ->defaultValue(600)
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('ios')
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('prod_service_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('dev_service_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('cert_dir')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('cert_pass')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()

                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }
    
    protected function addMapServiceNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('map_service');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('country')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
            
                ->arrayNode('providers')
                    ->children()

                        ->arrayNode('mapquest')
                            ->children()
                                ->scalarNode('class')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('javascript_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('javascript_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('webservice_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('webservice_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('openmapquest')
                            ->children()
                                ->scalarNode('class')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('javascript_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('javascript_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('webservice_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('webservice_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
            
                        ->arrayNode('google')
                            ->children()
                                ->scalarNode('class')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('javascript_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('javascript_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('webservice_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('webservice_url')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                
                ->arrayNode('geocoders')
                    ->performNoDeepMerging()
                    ->children()

                        ->arrayNode('US')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('provider')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
            
                                    ->arrayNode('tokens')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->prototype('scalar')->end()
                                    ->end()
            
                                    ->arrayNode('allowedQualityCodes')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->prototype('scalar')->end()
                                    ->end()

                                    ->scalarNode('batchSize')
										->defaultValue(1)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('CA')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('provider')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
            
                                    ->arrayNode('tokens')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->prototype('scalar')->end()
                                    ->end()
            
                                    ->arrayNode('allowedQualityCodes')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->prototype('scalar')->end()
                                    ->end()
                                
                                    ->scalarNode('batchSize')
										->defaultValue(1)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('GB')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('provider')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
            
                                    ->arrayNode('tokens')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->prototype('scalar')->end()
                                    ->end()
            
                                    ->arrayNode('allowedQualityCodes')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->prototype('scalar')->end()
                                    ->end()
                                
                                    ->scalarNode('batchSize')
										->defaultValue(1)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                    
                ->arrayNode('reverseGeocoders')
                    ->performNoDeepMerging()
                    ->children()

                        ->arrayNode('US')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('provider')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('CA')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('provider')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
            
                        ->arrayNode('GB')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('provider')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                
                ->arrayNode('routers')
                    ->performNoDeepMerging()
                    ->children()

                        ->arrayNode('US')
                            ->children()
                                ->scalarNode('provider')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('CA')
                            ->children()
                                ->scalarNode('provider')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()   
						
                        ->arrayNode('GB')
                            ->children()
                                ->scalarNode('provider')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()                
                
                ->arrayNode('javascript_apis')
                    ->performNoDeepMerging()
                    ->children()

                        ->arrayNode('US')
                            ->children()
                                ->scalarNode('provider')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('CA')
                            ->children()
                                ->scalarNode('provider')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()   
						
                        ->arrayNode('GB')
                            ->children()
                                ->scalarNode('provider')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()                  
            ->end();

        return $node;
    }
    
    protected function addLocalizationNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('localization');

        $node
            ->canBeDisabled()
        ->end();

        return $node;
    }

    protected function addMutexNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('mutex');

        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('dir')
                    ->defaultNull()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addUrlsNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('urls');

        $node
            ->useAttributeAsKey('namespace')
            ->prototype('array')
                ->children()
                    ->scalarNode('host')->isRequired()->end()
                    ->scalarNode('asset_path')->defaultNull()->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addViewNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('view');

        $node
            ->canBeEnabled()
            ->children()
                ->arrayNode('asset_dirs')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                            ->end()
                            ->scalarNode('path')
                                ->isRequired()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('use_lazy_loader')
                    ->defaultFalse()
                ->end()
                ->booleanNode('use_dynapart')
                    ->defaultFalse()
                ->end()
                ->arrayNode('js')
                    ->children()
                        ->arrayNode('translations')
                            ->prototype('scalar')
                                ->isRequired()
                            ->end()
                        ->end()
                        ->arrayNode('permissions')
                            ->children()
                                ->scalarNode('source')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('method')
                                    ->isRequired()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addCTAPINode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('ct_api');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('url')
                    ->isRequired()
                ->end()
                ->arrayNode('authenticators')
                    ->useAttributeAsKey('authenticationId')
                    ->prototype('scalar')
                    ->end()
                ->end()               
            ->end()
        ->end();

        return $node;
    }

    protected function addHtmlToPdfNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('html_to_pdf');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('wkhtmltopdf_path')
                    ->isRequired()
                ->end()
            ->end()
        ->end();

        return $node;
    }   

}

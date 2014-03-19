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
                ->append($this->addViewNode())
            ->end();

        return $tb;
    }

    protected function addLoggingNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('logging');

        $node
            ->canBeDisabled()
            ->children()
                ->enumNode('type')
                    ->values(array('sqlite','tab'))
                    ->isRequired()
                ->end()
                ->enumNode('level')
                    ->values(array('debug','info','warn','error','alert'))
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
            ->canBeDisabled()
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
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
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
                                // ->arrayNode('certs')
                                //     ->prototype('array')
                                //         ->children()
                                //             ->scalarNode('package_id')
                                //                 ->isRequired()
                                //                 ->cannotBeEmpty()
                                //             ->end()
                                //             ->scalarNode('service_url')
                                //                 ->isRequired()
                                //                 ->cannotBeEmpty()
                                //             ->end()
                                //         ->end()
                                //     ->end()
                                // ->end()
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
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('countries')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('allowedQualityCodes')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->prototype('scalar')->end()
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
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('dir')
                    ->defaultNull()
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
            ->canBeDisabled()
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
                ->arrayNode('js_default_translations')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }
}
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
                ->append($this->addCacheManagerNode())
                ->append($this->addSimpleCacheNode())
                ->append($this->addEntityFilterCacheNode())
                ->append($this->addProcessLockNode())
                ->append($this->addLoggingNode())
                ->append($this->addSystemAlertsNode())
                ->append($this->addXhrExceptionListenerNode())
                ->append($this->addRedirectExceptionListenerNode())
                ->append($this->addRouteInspectorNode())
                ->append($this->addOrmNode())
                ->append($this->addSharedCacheNode())
                ->append($this->addEncryptNode())
                ->append($this->addMapServiceNode())
                ->append($this->addSessionSignatureCheckNode())
                ->append($this->addLocalizationNode())
                ->append($this->addPushNode())
                ->append($this->addCsrfNode())
                ->append($this->addMutexNode())
                ->append($this->addUrlsNode())
                ->append($this->addViewNode())
                ->append($this->addCTAPINode())
                ->append($this->addHtmlToPdfNode())
                ->append($this->addActionLoggerNode())
                ->append($this->addFilteredObjectIndexNode())
                ->append($this->addWebServiceRequestAuthenticationNode())
                ->append($this->addMySqlSecureShellNode())
                ->append($this->addHipChatNode())
                ->append($this->addInputSanitizationListenerNode())
                ->append($this->addAwsS3Node())
            ->end();

        return $tb;
    }

    protected function addAwsS3Node()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('aws_s3');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('region')
                    ->isRequired()
                ->end()
                ->scalarNode('bucket')
                    ->isRequired()
                ->end()
                ->scalarNode('key')
                    ->isRequired()
                ->end()
                ->scalarNode('secret')
                    ->isRequired()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addCacheManagerNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('cache');

        $node
            ->canBeEnabled()
            ->children()
                ->arrayNode('managers')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addSimpleCacheNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('simple_cache');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('namespace')
                    ->isRequired()
                ->end()
                ->scalarNode('redis_client')
                    ->isRequired()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addEntityFilterCacheNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('entity_filter_cache');

        $node
            ->canBeEnabled()
            ->children()
                ->arrayNode('entities')
                    ->info('Define each entity filter cache group')
                    ->useAttributeAsKey('entityName')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('namespace')
                                ->info('The key namespace used to prevent collisions with other redis keys')
                                ->isRequired()
                            ->end()
                            ->scalarNode('redis_client')
                                ->info('The service ID for the redis client')
                                ->isRequired()
                            ->end()
                            ->scalarNode('ttl')
                                ->info('The number of seconds to limit the lifetime of data in cache')
                                ->isRequired()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    protected function addProcessLockNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('process_lock');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('redis_client')
                    ->isRequired()
                ->end()
                ->scalarNode('namespace')
                    ->defaultNull()
                ->end()
            ->end()
        ->end();

        return $node;
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

    protected function addXhrExceptionListenerNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('xhr_exception_listener');

        $node
            ->canBeEnabled()
            ->children()
                ->booleanNode('invalidate_session')
                    ->defaultFalse()
                    ->info('Indicates whether to invalidate session when not debug')
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addRedirectExceptionListenerNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('redirect_exception_listener');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('redirect_to')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Indicates where to redirect browser')
                ->end()
                ->booleanNode('invalidate_session')
                    ->defaultFalse()
                    ->info('Indicates whether to invalidate session')
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

    protected function addCsrfNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('csrf');

        $node
            ->canBeEnabled()
            ->children()
                ->booleanNode('enforce_check')
                    ->defaultFalse()
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

                                    ->arrayNode('validatedTokenChecks')
                                        ->prototype('scalar')->end()
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

                                    ->arrayNode('validatedTokenChecks')
                                        ->prototype('scalar')->end()
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

                                    ->arrayNode('validatedTokenChecks')
                                        ->prototype('scalar')->end()
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

                ->arrayNode('timeZoners')
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

    protected function addSessionSignatureCheckNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('session_signature_check');

        $node
            ->canBeEnabled()
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
                        ->arrayNode('routes')
                            ->prototype('scalar')
                                ->isRequired()
                            ->end()
                        ->end()
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

    protected function addActionLoggerNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('action_log');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('entity_manager')
                    ->isRequired()
                ->end()
                ->scalarNode('source')
                    ->isRequired()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addFilteredObjectIndexNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('filtered_object_index');

        $node
            ->canBeEnabled()
            ->children()
                ->arrayNode('groups')
                    ->info('Define each filtered object index group')
                    ->useAttributeAsKey('groupName')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('key_namespace')
                                ->info('The key namespace used to prevent collisions with other redis keys')
                                ->isRequired()
                            ->end()
                            ->scalarNode('redis_client')
                                ->info('The service ID for the redis client')
                                ->isRequired()
                            ->end()
                            ->arrayNode('indexes')
                                ->info('The list of indexes in this group')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    protected function addInputSanitizationListenerNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('input_sanitization_listener');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('redirect')
                    ->info('The redirect URL to be used when validation fails')
                    ->isRequired()
                ->end()
                ->booleanNode('invalidate_session')
                    ->defaultFalse()
                    ->info('Indicates whether to invalidate session')
                ->end()
            ->end();

        return $node;
    }

    protected function addWebServiceRequestAuthenticationNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('web_service_authentication');

        $node
            ->canBeEnabled()
            ->end();

        return $node;
    }

    protected function addMySqlSecureShellNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('mysql_secure_shell');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('mysql_binary_path')
                    ->info('Absolute path to mysql binary')
                    ->defaultValue('/usr/bin/mysql')
                ->end()
                ->scalarNode('temp_dir_path')
                    ->info('The path where temporary query files will be saved')
                    ->defaultValue('/tmp')
                ->end()
                ->arrayNode('accounts')
                ->info('Define each secure account')
                ->useAttributeAsKey('accountName')
                ->isRequired()
                ->requiresAtLeastOneElement()
                ->prototype('array')
                    ->children()
                        ->scalarNode('username_file')
                            ->info('The path to the database username file')
                            ->isRequired()
                        ->end()
                        ->scalarNode('password_file')
                            ->info('The path to the database password file')
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    protected function addHipChatNode()
    {
        $tb = new TreeBuilder;
        $node = $tb->root('hipchat');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('group_name')
                    ->info('The name of your HipChat group')
                    ->isRequired()
                ->end()
                ->booleanNode('disable_delivery')
                    ->info('Indicates whether to disable notification delivery')
                    ->defaultFalse()
                ->end()
                ->arrayNode('notifiers')
                    ->info('The set of supported HipChat notifiers')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('notifierName')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('room')
                                ->info('The name of the HipChat room')
                                ->isRequired()
                            ->end()
                            ->scalarNode('token')
                                ->info('The HipChat authentication token')
                                ->isRequired()
                            ->end()
                        ->end()
                    ->end()
                ->end();

            return $node;
        }

}

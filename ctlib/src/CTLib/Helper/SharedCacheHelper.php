<?php
namespace CTLib\Helper;

use CTLib\Util\Arr;

/**
 * Enables cache shared by all sessions.
 * NOTE: Currently requires Memcache. If Memcache not available, cache won't throw an error, but it also won't store any values.
 * NOTE: Won't enable if environment is in $disabledEnvironments.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class SharedCacheHelper
{
    
    const CONFIG_PARAMETER_KEY_PREFIX = "ctlib.shared_cache.";


    protected $isExplicitlyEnabled;
    protected $servers;
    protected $logger;
    protected $keyPrefix;
    protected $cacheHandler;


    /**
     * @param mixed $config     Can be either:
     *                              False
     *                                  -> disables cache.
     *                              array('enabled' => boolean, 'servers' => array())
     *                                  -> inits cache using array values.
     *                              Container
     *                                  -> inits cache using Symfony service container.
     * @param string|object|null $keyPrefix     If string, uses as explicit cache
     *                                          key prefix.
     *                                          If object, uses result of
     *                                          $object->getSharedCacheKeyPrefix.
     *                                          If null, won't use key prefix.
     */
    public function __construct($config, $keyPrefix=null)
    {
        if ($config === false) {
            $this->isExplicitlyEnabled = false;
        } elseif (is_array($config)) {
            // Initializing outside of Symfony service layer.
            // Must receive array with 'enabled' and 'servers' keys.
            $this->isExplicitlyEnabled  = Arr::mustGet('enabled', $config);
            $this->servers              = Arr::mustGet('servers', $config);
        } else {
            $this->isExplicitlyEnabled  = $this->getConfigParameter(
                                            $config,
                                            'enabled');
            $this->servers              = $this->getConfigParameter(
                                            $config,
                                            'servers') ?: array();
            $this->logger               = $config->get('logger');
        }

        if ($keyPrefix) {
            if (is_object($keyPrefix)) {
                $this->keyPrefix = $keyPrefix->getSharedCacheKeyPrefix();
            } elseif (is_string($keyPrefix)) {
                $this->keyPrefix = $keyPrefix;
            } else {
                throw new \Exception('$keyPrefix must be object or string');
            }
        } else {
            $this->keyPrefix = null;
        }
    }

    /**
     * Gets parameter from service container.
     *
     * @param Container $container
     * @param string $key   Automtically namespaces $key for this extension.
     * @return mixed
     */
    protected function getConfigParameter($container, $key)
    {
        $qualifiedKey = self::CONFIG_PARAMETER_KEY_PREFIX . $key;
        if (! $container->hasParameter($qualifiedKey)) {
            return null;
        }
        return $container->getParameter($qualifiedKey);
    }

    /**
     * Initializes the cache handler (if not already) and returns it.
     *
     * @return mixed    Either returns cache handler object or FALSE if cache
     *                  shouldn't/couldn't be enabled.
     */
    protected function cache()
    {
        if (isset($this->cacheHandler)) { return $this->cacheHandler; }

        if (! $this->isExplicitlyEnabled) {
            $this->cacheHandler = false;
        } elseif (! $this->servers) {
            $this->cacheHandler = false;
            $this->logWarning("SharedCacheHelper requires at least 1 cache server");
        } elseif ($missingDependencies = $this->getMissingDependencies()) {
            $this->cacheHandler = false;
            $this->logWarning("SharedCacheHelper requires the following: " . join(', ', $missingDependencies));
        } else {
            $this->initCacheHandler();
        }
        return $this->cacheHandler;
    }

    /**
     * Returns names of missing dependencies required by the cache handler.
     *
     * @return array
     */
    protected function getMissingDependencies()
    {
        return class_exists('\Memcache') ? array() : array('\Memcache');
    }

    /**
     * Initializes the cache handler.
     *
     * @return void
     */
    protected function initCacheHandler()
    {
        $this->cacheHandler = new \Memcache;
        foreach ($this->servers as $server) {
            $this->cacheHandler->addServer($server);
        }
    }

    /**
     * Sets value in cache.
     *
     * @param string $key       Unique key to reference value.
     * @param mixed $value
     * @param int $ttl          Value's minutes to live in cache before
     *                          expiring. If 0, value will not expire.
     *                          
     * @return void
     */
    public function set($key, $value, $ttl=0)
    {
        if (! $this->isEnabled()) { return; }
        
        $ttlSeconds = $ttl * 60;
        try {
            $this
                ->cache()
                ->set(
                    $this->formatQualifiedCacheKey($key),
                    $value,
                    0,
                    $ttlSeconds);    
        } catch (\Exception $e) {
            $this->logWarning((string) $e);
        }
    }

    /**
     * Gets value from cache.
     *
     * @param string $key       Unique key to reference value.
     * @return mixed            Returns NULL if key not found in cache.
     */
    public function get($key)
    {
        if (! $this->isEnabled()) { return null; }

        try {
            $result = $this->cache()->get($this->formatQualifiedCacheKey($key));
            return $result !== false ? $result : null;    
        } catch (\Exception $e) {
            $this->logWarning((string) $e);
            return null;
        }        
    }

    /**
     * Indicates whether value exists in cache.
     * 
     * @param string $key       Unique key to reference value.
     * @return boolean
     */
    public function has($key)
    {
        if (! $this->isEnabled()) { return false; }

        try {
            return $this->get($this->formatQualifiedCacheKey($key)) !== null;    
        } catch (\Exception $e) {
            $this->logWarning((string) $e);
            return false;
        }
    }

    /**
     * Deletes value from cache.
     *
     * @param string $key       Unique key to reference value.
     * @return void
     */
    public function delete($key)
    {
        if (! $this->isEnabled()) { return; }

        try {
            $this->cache()->delete($this->formatQualifiedCacheKey($key));    
        } catch (\Exception $e) {
            $this->logWarning((string) $e);
        }
    }

    /**
     * Adds brand and app version namespace to cache key.
     *
     * @param string $key
     * @return string
     */
    protected function formatQualifiedCacheKey($key)
    {
        return ($this->keyPrefix) ? "{$this->keyPrefix}.{$key}" : $key;
    }

    /**
     * Indicates whether cache is enabled.
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->cache() !== false;
    }

    /**
     * Logs warning message.
     *
     * @param string $msg
     * @return boolean      Returns TRUE if cache initialized with logger.
     */
    protected function logWarning($msg)
    {
        if (! $this->logger) { return false; }
        $this->logger->addWarning($msg);
        return true;
    }


}
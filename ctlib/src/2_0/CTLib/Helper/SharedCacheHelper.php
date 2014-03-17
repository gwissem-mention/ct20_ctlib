<?php
namespace CTLib\Helper;

/**
 * Enables cache shared by all sessions.
 *
 * NOTE: Currently requires Memcache. If Memcache is not available, this service
 * won't throw an error, but it also won't store any values.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class SharedCacheHelper
{
    
    const MAX_TTL_SECONDS = 2592000; // 30 days

    /**
     * @var boolean
     */
    protected $isExplicitlyEnabled;

    /**
     * @var array
     */
    protected $servers;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $keyPrefix;

    /**
     * @var Memcache
     */
    protected $cacheHandler;


    /**
     * @param boolean $isExplicitlyEnabled
     * @param array $servers
     * @param Logger $logger
     */
    public function __construct(
                        $isExplicitlyEnabled,
                        $servers=array(),
                        $logger=null)
    {
        $this->isExplicitlyEnabled  = $isExplicitlyEnabled;
        $this->servers              = $servers;
        $this->logger               = $logger;
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
        // Ensure that we didn't exceed max ttl allowed. Using invalid ttl will
        // prevent value from saving to cache.
        $ttlSeconds = min($ttlSeconds, self::MAX_TTL_SECONDS);

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
            $this
                ->cache()
                ->set($this->formatQualifiedCacheKey($key), '', 0, -1);
        } catch (\Exception $e) {
            $this->logWarning((string) $e);
        }
    }

    /**
     * Empties cache.
     *
     * @param string $prefix    If passed, will only flush items with keys that
     *                          start with prefix. If null, will flush all items.
     * @return integer|null     If $prefix passed, will return number of flushed
     *                          items. Otherwise returns void.
     */
    public function flush($prefix=null)
    {
        if (! $this->isEnabled()) {
            return $prefix ? 0 : null;
        }

        if (! $prefix) {
            $this->cache()->flush();
            return null;
        }

        $numDeleted = 0;
        foreach ($this->getKeys($prefix) as $key) {
            $this->delete($key);
            $numDeleted += 1;
        }
        return $numDeleted;
    }

    /**
     * Returns keys for all cached items.
     *
     * @param string $prefix    If passed, will only return keys that start with
     *                          prefix. If null, returns all keys.
     * @return array
     */
    public function getKeys($prefix=null)
    {
        if (! $this->isEnabled()) { return array(); }
        
        $keys = array();

        foreach ($this->cache()->getExtendedStats('slabs') as $server => $slabs) {
            foreach ($slabs as $slabId => $slab) {
                if (is_int($slabId)) {
                    $slabDetails = $this
                                    ->cache()
                                    ->getExtendedStats('cachedump', $slabId);
                    foreach ($slabDetails as $server => $items) {
                        if (! is_array($items)) { continue; }
                        foreach ($items as $key => $value) {
                            if (! $prefix || strpos($key, $prefix) === 0) {
                                $keys[] = $key;
                            }
                        }
                    }
                }
            }
        }
        return $keys;
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
     * Sets global cache key prefix.
     *
     * @param string $keyPrefix
     * @return void
     */
    public function setKeyPrefix($keyPrefix)
    {
        $this->keyPrefix = $keyPrefix;
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
<?php

namespace CTLib\Component\Cache;

use CTLib\Component\Monolog\Logger;
use CellTrak\RedisBundle\Component\Client\CellTrakRedis;

/**
* Wrapper class to use redis to store cached data.
*
* @author David McLean <dmclean@celltrak.com>
*/
class SimpleCache
{
    /**
     * @var string $namespace
     */
    protected $namespace;

    /**
     * @var CellTrakRedis $redis
     */
    protected $redis;

    /**
     * @var string $cacheKey
     */
    protected $cacheKey;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * @param string        $namespace
     * @param CellTrakRedis $redis
     * @param Logger $logger
     */
    public function __construct(
        $namespace,
        CellTrakRedis $redis,
        Logger $logger
    ) {
        $this->namespace   = $namespace;
        $this->redis       = $redis;
        $this->cacheKey    = "ssc:" . $namespace;
        $this->logger      = $logger;
    }

    /**
    * Set an entry in cache.
    *
    * @param string $key
    * @param string $value
    */
    public function set($key, $value)
    {
        try {
            $this->redis->hSet($this->cacheKey, $key, $value);
        } catch (\Exception $ex) {
            $this->logger->warn('SimpleCache: failed to write to Redis');
        }
    }

    /**
    * Get an entry from cache.
    *
    * @param string $key
    *
    * @return string|null
    */
    public function get($key)
    {
        try {
            return $this->redis->hGet($this->cacheKey, $key);
        } catch (\Exception $ex) {
            $this->logger->warn('SimpleCache: failed to read from Redis');
            return null;
        }
    }

    /**
    * Delete an entry from the cache.
    *
    * @param string $key
    *
    * @return bool
    */
    public function delete($key)
    {
        try {
            return $this->redis->hDel($this->cacheKey, $key) > 0;
        } catch (\Exception $ex) {
            $this->logger->warn('SimpleCache: failed to delete from Redis');
            return false;
        }
    }

    /**
     * Test if an entry exists in the cache.
     *
     * @param string $key The cache id of the entry to check for.
     *
     * @return boolean TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    public function contains($key)
    {
        try {
            return $this->redis->hExists($this->cacheKey, $key);
        } catch (\Exception $ex) {
            $this->logger->warn('SimpleCache: failed to read from Redis');
            return false;
        }
    }

    /**
     * @return string
     */
    public function getStats()
    {
        try {
            $info = $this->redis->info();
            return 'Uptime: '.$info['uptime_in_seconds']
                .'Memory Usage: '.$info['used_memory'];
        } catch (\Exception $ex) {
            $this->logger->warn('SimpleCache: failed to read from Redis');
            return 'NA';
        }
    }

    /**
     * Clear ALL the contents of the SSC Key.
     *
     * @return string|null
     */
    public function flush()
    {
        try {
            return $this->redis->del($this->cacheKey);
        } catch (\Exception $ex) {
            $this->logger->warn('SimpleCache: failed to read from Redis');
            return null;
        }
    }
}

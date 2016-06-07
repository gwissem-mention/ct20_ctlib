<?php

namespace CTLib\Component\Cache;

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
     * @param string        $namespace
     * @param CellTrakRedis $redis
     */
    public function __construct($namespace, CellTrakRedis $redis)
    {
        $this->siteId   = $namespace;
        $this->redis    = $redis;
        $this->cacheKey = "ssc:" . $namespace;
    }

    /**
    * Set an entry in cache.
    *
    * @param string $key
    * @param string $value
    */
    public function set($key, $value)
    {
        $this->redis->hSet($this->cacheKey, $key, $value);
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
        return $this->redis->hGet($this->cacheKey, $key);
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
        return $this->redis->hDel($this->cacheKey, $key) > 0;
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
        return $this->redis->hExists($this->cacheKey, $key);
    }

    /**
     * @return string
     */
    public function getStats()
    {
        $info = $this->redis->info();
        return 'Uptime: '.$info['uptime_in_seconds']
            .'Memory Usage: '.$info['used_memory'];
    }
}

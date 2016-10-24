<?php

namespace CTLib\Component\Cache;

use CellTrak\RedisBundle\Component\Client\CellTrakRedis;

/**
* Wrapper class to use redis to store cached filter data.
*
* @author David McLean <dmclean@celltrak.com>
*/
abstract class FilterCache
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
     * @var int $ttl
     */
    protected $ttl;

    /**
     * @var string $cacheKeyPrefix
     */
    private $cacheKeyPrefix;


    /**
     * @param string        $namespace
     * @param string        $class
     * @param CellTrakRedis $redis
     * @param int           $ttl
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $namespace,
        $class,
        CellTrakRedis $redis,
        $ttl
    ) {
        if (!$namespace) {
            throw new \InvalidArgumentException("Invalid namespace");
        }

        if (!$class) {
            throw new \InvalidArgumentException("Invalid class");
        }

        $this->namespace      = $namespace;
        $this->redis          = $redis;
        $this->ttl            = $ttl;
        $this->cacheKeyPrefix = "fc:$namespace:$class:";
    }

    /**
    * Set an entry in cache.
    *
    * @param int   $key
    * @param array $value
    */
    public function set($key, array $value)
    {
        $this->redis->setex(
            $this->getCacheKey($key),
            implode(',', $value)
            $this->ttl
        );
    }

    /**
    * Get an entry from cache.
    *
    * @param int $key
    *
    * @return array|null
    */
    public function get($key)
    {
        $vals = $this->redis->get($this->getCacheKey($key));
        if ($vals) {
            return explode(',', $vals);
        }
        return null;
    }

    /**
    * Delete an entry from the cache.
    *
    * @param int $key
    *
    * @return bool
    */
    public function delete($key)
    {
        return $this->redis-del($this->getCacheKey($key)) > 0;
    }

    /**
     * Flush all entries for a hash.
     *
     * @return int
     */
    public function flush()
    {
        return $this->redis->del($this->cacheKeyPrefix.'*');
    }

    /**
     * Test if an entry exists in the cache.
     *
     * @param int $key
     *
     * @return boolean
     */
    public function containsKey($key)
    {
        return $this->redis->exists($this->getCacheKey($key));
    }

    /**
     * @param int $key
     *
     * @return string
     */
    protected function getCacheKey($key)
    {
        return $this->cacheKeyPrefix . $key;
    }
}

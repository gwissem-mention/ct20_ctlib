<?php

namespace CTLib\Component\Cache;

use CellTrak\RedisBundle\Component\Client\CellTrakRedis;

/**
* Wrapper class to use redis to store cached filter data.
*
* @author David McLean <dmclean@celltrak.com>
*/
class EntityFilterCache implements CachedComponentInterface
{
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
     * @param string        $entityClass
     * @param string        $namespace
     * @param CellTrakRedis $redis
     * @param int           $ttl
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $entityClass,
        $namespace,
        CellTrakRedis $redis,
        $ttl
    ) {
        $this->redis          = $redis;
        $this->ttl            = $ttl;
        $this->cacheKeyPrefix = "fc:$namespace:$entityClass:";
    }

    /**
    * Set an entry in cache.
    *
    * @param int   $entityId
    * @param array $filterIds
    */
    public function setFilterIds($entityId, array $filterIds)
    {
        $this->redis->setex(
            $this->compileCacheKey($entityId),
            $this->ttl,
            json_encode($filterIds)
        );
    }

    /**
    * Get an entry from cache.
    *
    * @param int $entityId
    *
    * @return array|null
    */
    public function getFilterIds($entityId)
    {
        $filterIds = $this->redis->get($this->compileCacheKey($entityId));

        if (!$filterIds) {
            return null;
        }

        return json_decode($filterIds, true);
    }

    /**
    * Delete an entry from the cache.
    *
    * @param int $entityId
    *
    * @return int
    */
    public function deleteFilterIds($entityId)
    {
        return $this->redis->del($this->compileCacheKey($entityId)) > 0;
    }

    /**
     * Test if an entry exists in the cache.
     *
     * @param int $entityId
     *
     * @return boolean
     */
    public function containsEntityId($entityId)
    {
        return $this->redis->exists($this->compileCacheKey($entityId));
    }

    /**
     * {@inheritdoc}
     */
    public function warmCache()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function flushCache()
    {
        $keys = $this->redis->scanForKeys(
            $this->cacheKeyPrefix . '*'
        );
        return $this->redis->del($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function inspectCache()
    {
        $keys = $this->redis->scanForKeys(
            $this->cacheKeyPrefix . '*'
        );

        $startPos = strlen($this->cacheKeyPrefix.':');

        $content .= "$entity:" . PHP_EOL;

        foreach ($keys as $key) {
            $entityId = substr($key, $startPos);
            $content .= str_pad("   $entityId", 12) . " => Filters: "
                . $this->redis->get($this->compileCacheKey($entityId))
                . PHP_EOL;
        }
        $content .= PHP_EOL;

        return ['content' => $content];
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDescription()
    {
        return "Manage cache for entity filters";
    }

    /**
     * @param int $entityId
     *
     * @return string
     */
    protected function compileCacheKey($entityId)
    {
        return $this->cacheKeyPrefix . $entityId;
    }
}

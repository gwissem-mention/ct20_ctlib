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
     * @var string $namespace
     */
    protected $namespace;

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
     * @param CellTrakRedis $redis
     * @param int           $ttl
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $namespace,
        CellTrakRedis $redis,
        $ttl
    ) {
        $this->redis          = $redis;
        $this->namespace      = $namespace;
        $this->ttl            = $ttl;
        $this->cacheKeyPrefix = "fc:";
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
        // Warming cache for entity filters is not supported.
    }

    /**
     * {@inheritdoc}
     */
    public function flushCache()
    {
        $keys = $this->redis->scanForKeys(
            $this->cacheKeyPrefix . $this->namespace . ':*'
        );
        return $this->redis->del($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function inspectCache()
    {
        $keys = $this->redis->scanForKeys(
            $this->cacheKeyPrefix . $this->namespace . ':*'
        );

        $startPos = strpos($this->namespace, ':');
        $class = substr($this->namespace, $startPos + 1);
        $startPos = strlen($this->cacheKeyPrefix . $this->namespace . ':');

        $content = ucwords("$class:", "_") . PHP_EOL;
        $content = str_replace("_", "", $content);

        foreach ($keys as $key) {
            $entityId = substr($key, $startPos);
            $content .= str_pad("   $entityId", 12) . " => Filters: "
                . $this->redis->get($this->compileCacheKey($entityId))
                . PHP_EOL.PHP_EOL;
        }

        return ['content' => $content];
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDescription()
    {
        return "Manage cache for entity {$this->namespace} filters";
    }

    /**
     * @param int $entityId
     *
     * @return string
     */
    protected function compileCacheKey($entityId)
    {
        return $this->cacheKeyPrefix . $this->namespace . ":$entityId";
    }
}

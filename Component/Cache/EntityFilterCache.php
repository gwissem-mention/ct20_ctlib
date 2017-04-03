<?php

namespace CTLib\Component\Cache;

use CTLib\Component\Monolog\Logger;
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
     * @var Logger $logger
     */
    protected $logger;

    /**
     * @param string        $namespace
     * @param CellTrakRedis $redis
     * @param int           $ttl
     * @param Logger        $logger
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $namespace,
        CellTrakRedis $redis,
        $ttl,
        Logger $logger
    ) {
        $this->redis          = $redis;
        $this->namespace      = $namespace;
        $this->ttl            = $ttl;
        $this->cacheKeyPrefix = "fc:";
        $this->logger         = $logger;
    }

    /**
    * Set an entry in cache.
    *
    * @param int   $entityId
    * @param array $filterIds
    */
    public function setFilterIds($entityId, array $filterIds)
    {
        try {
            $this->redis->setex(
                $this->compileCacheKey($entityId),
                $this->ttl,
                json_encode($filterIds)
            );
        } catch (\Exception $ex) {
            $this->logger->warn('EntityFilterCache: failed to write to Redis');
        }
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
        try {
            $filterIds = $this->redis->get($this->compileCacheKey($entityId));
        } catch (\Exception $ex) {
            $this->logger->warn('EntityFilterCache: failed to read from Redis');
            return null;
        }

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
        try {
            return $this->redis->del($this->compileCacheKey($entityId)) > 0;
        } catch (\Exception $ex) {
            $this->logger->warn('EntityFilterCache: failed to delete from Redis');
            return 0;
        }
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
        try {
            return $this->redis->exists($this->compileCacheKey($entityId));
        } catch (\Exception $ex) {
            $this->logger->warn('EntityFilterCache: failed to read from Redis');
            return false;
        }
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
        try {
            $keys = $this->redis->scanForKeys(
                $this->cacheKeyPrefix . $this->namespace . ':*'
            );
            return $this->redis->del($keys);
        } catch (\Exception $ex) {
            $this->logger->warn('EntityFilterCache: failed to delete from Redis');
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function inspectCache()
    {
        try {
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
        } catch (\Exception $ex) {
            $this->logger->warn('EntityFilterCache: failed to read from Redis');
            return ['content' => 'NA'];
        }
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

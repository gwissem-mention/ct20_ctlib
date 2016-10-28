<?php

namespace CTLib\Component\Cache;

use CellTrak\RedisBundle\Component\Client\CellTrakRedis;
use CTLib\Component\Doctrine\ORM\EntityManager;

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
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var int $ttl
     */
    protected $ttl;

    /**
     * @var string $cacheKeyPrefix
     */
    private $cacheKeyPrefix;

    /**
     * @var array
     */
    protected $entities;


    /**
     * @param string        $namespace
     * @param CellTrakRedis $redis
     * @param EntityManager $entityManager
     * @param int           $ttl
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $namespace,
        CellTrakRedis $redis,
        EntityManager $entityManager,
        $ttl
    ) {
        $this->redis          = $redis;
        $this->entityManager  = $entityManager;
        $this->ttl            = $ttl;
        $this->cacheKeyPrefix = "fc:$namespace:";
        $this->entities       = [];
    }

    /**
     * Adds a supported entity name.
     *
     * @param string $entityName
     *
     * @return void
     */
    public function addEntity($entityName)
    {
        if (!$this->supportsEntity($entityName)) {
            $this->entities[] = $entityName;
        }
    }

    /**
     * Indicates whether we are currently supporting
     * the given entity name.
     *
     * @param string $entityName
     *
     * @return boolean
     */
    public function supportsEntity($entityName)
    {
        return in_array($entityName, $this->entities);
    }

    /**
     * Returns names of all currently supported entities.
     *
     * @return array
     */
    public function getEntities()
    {
        return $this->entities;
    }

    /**
    * Set an entry in cache.
    *
    * @param $entity
    * @param array $filterIds
    */
    public function setFilterIds($entity, array $filterIds)
    {
        $class = $this->getClassName($entity);

        if (!$this->supportsEntity($class)) {
            throw new \InvalidArgumentException("Unsupported entity - $class");
        }

        $entityId = $this->getEntityId($entity);

        $this->redis->setex(
            $this->compileCacheKey($class, $entityId),
            $this->ttl,
            json_encode($filterIds)
        );
    }

    /**
    * Get an entry from cache.
    *
    * @param $entity
    *
    * @return array|null
    */
    public function getFilterIds($entity)
    {
        $class = $this->getClassName($entity);

        if (!$this->supportsEntity($class)) {
            throw new \InvalidArgumentException("Unsupported entity - $class");
        }

        $entityId = $this->getEntityId($entity);

        $filterIds = $this->redis->get(
            $this->compileCacheKey($class, $entityId)
        );

        if (!$filterIds) {
            return null;
        }

        return json_decode($filterIds, true);
    }

    /**
    * Delete an entry from the cache.
    *
    * @param $entity
    *
    * @return int
    */
    public function deleteFilterIds($entity)
    {
        $class = $this->getClassName($entity);

        if (!$this->supportsEntity($class)) {
            throw new \InvalidArgumentException("Unsupported entity - $class");
        }

        $entityId = $this->getEntityId($entity);

        return $this->redis->del(
            $this->compileCacheKey($class, $entityId)
        ) > 0;
    }

    /**
     * Test if an entry exists in the cache.
     *
     * @param $entity
     *
     * @return boolean
     */
    public function containsEntityId($entity)
    {
        $class = $this->getClassName($entity);

        if (!$this->supportsEntity($class)) {
            throw new \InvalidArgumentException("Unsupported entity - $class");
        }

        $entityId = $this->getEntityId($entity);

        return $this->redis->exists($this->compileCacheKey($class, $entityId[0]));
    }

    /**
     * {@inheritdoc}
     */
    public function warmCache()
    {
        throw new \RuntimeException('warmCache not supported for EntityFilterCache.');
    }

    /**
     * {@inheritdoc}
     */
    public function flushCache()
    {
        $count = 0;

        foreach ($this->entities as $entity) {
            $keys = $this->redis->scanForKeys(
                $this->cacheKeyPrefix . $entity . ':*'
            );
            $count += $this->redis->del($keys);
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function inspectCache()
    {
        $content = '';

        foreach ($this->entities as $entity) {
            $content .= "$entity:" . PHP_EOL;

            $keys = $this->redis->scanForKeys(
                $this->cacheKeyPrefix . $entity . ':*'
            );

            $startPos = strlen($this->cacheKeyPrefix.$entity.':');

            foreach ($keys as $key) {
                $entityId = substr($key, $startPos);
                $content .= str_pad("   $entityId", 12) . " => Filters: "
                    . $this->redis->get($this->compileCacheKey($entity, $entityId))
                    . PHP_EOL;
            }
            $content .= PHP_EOL;
        }

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
     * @param string $class
     * @param int $entityId
     *
     * @return string
     */
    protected function compileCacheKey($class, $entityId)
    {
        return $this->cacheKeyPrefix . $class . ':' . $entityId;
    }

    /**
     * Helper to get the given entity's primary id.
     *
     * @param $entity
     *
     * @return boolean
     */
    protected function getEntityId($entity)
    {
        if (method_exists($entity, 'getEntityId')) {
            return $entity->getEntityId();
        } else {
            $entityId = $this->entityManager->getEntityLogicalId($entity);
            $entityId = array_values($entityId);
            return $entityId[0];
        }
    }

    /**
     * Helper to get the class name without the namespance.
     *
     * @param $entity
     *
     * @return string
     */
    protected function getClassName($entity)
    {
        $className = get_class($entity);
        $pos = strrpos($className, "\\");
        if ($pos === false) {
            return null;
        }
        return substr($className, $pos + 1);
    }
}

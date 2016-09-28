<?php
namespace CTLib\Component\FilteredObjectIndex;

use Celltrak\RedisBundle\Component\Client\CellTrakRedis;
use CTLib\Component\Monolog\Logger;


class FilteredObjectIndexGroup
{

    public function __construct($keyPrefix, CellTrakRedis $redis, Logger $logger)
    {
        $this->keyPrefix    = $keyPrefix;
        $this->redis        = $redis;
        $this->logger       = $logger;
        $this->indexes      = [];
    }

    public function addIndex($index)
    {
        if (!$this->hasIndex($index)) {
            $this->indexes[] = $index;
        }
    }

    public function hasIndex($index)
    {
        return in_array($index, $this->indexes);
    }

    public function getIndexes()
    {
        return $this->indexes;
    }

    public function addObjectToIndex($index, $objectId, array $filterIds = [])
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        $indexKeyPrefix = $this->qualifyCacheKey($index);

        $this->redis->multi(\Redis::PIPELINE);
        $this->redis->sAdd($indexKeyPrefix . ':global', $objectId);

        foreach ($filterIds as $filterId) {
            $this->redis->sAdd($indexKeyPrefix . ":{$filterId}", $objectId);
        }
        $results = $this->redis->exec();

        return in_array(1, $results);
    }

    public function removeObjectFromIndex($index, $objectId)
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        $indexKeys = $this->getIndexKeys($index);

        $this->redis->multi(\Redis::PIPELINE);
        foreach ($indexKeys as $indexKey) {
            $this->redis->sRem($indexKey, $objectId);
        }
        $results = $this->redis->exec();

        return in_array(1, $results);
    }

    public function removeObjectFromAllIndexes($objectId)
    {
        $groupKeys = $this->getGroupKeys();

        $this->redis->multi(\Redis::PIPELINE);
        foreach ($groupKeys as $groupKey) {
            $this->redis->sRem($groupKey, $objectId);
        }
        $results = $this->redis->exec();

        $results = array_combine($groupKeys, $results);
        $removedFromIndexes = [];

        foreach ($this->indexes as $index) {
            $indexGlobalKey = $this->qualifyCacheKey($index) . ':global';
            if (isset($results[$indexGlobalKey]) && $results[$indexGlobalKey]) {
                $removedFromIndexes[] = $index;
            }
        }
        return $removedFromIndexes;
    }

    public function moveObjectToIndex($index, $objectId, array $filterIds = [])
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        $groupKeys = $this->getGroupKeys();
        $usedKeys = $groupKeys;

        $this->redis->multi(\Redis::PIPELINE);
        foreach ($groupKeys as $groupKey) {
            $this->redis->sRem($groupKey, $objectId);
        }

        $indexKeyPrefix = $this->qualifyCacheKey($index);
        $this->redis->sAdd($indexKeyPrefix . ':global', $objectId);
        $usedKeys[] = 'global';

        foreach ($filterIds as $filterId) {
            $this->redis->sAdd($indexKeyPrefix . ":{$filterId}", $objectId);
            $usedKeys[] = $filterId;
        }

        $results = $this->redis->exec();

        $results = array_combine($usedKeys, $results);
        $removedFromIndexes = [];

        foreach ($this->indexes as $index) {
            $indexGlobalKey = $this->qualifyCacheKey($index) . ':global';
            if (isset($results[$indexGlobalKey]) && $results[$indexGlobalKey]) {
                $removedFromIndexes[] = $index;
            }
        }
        return $removedFromIndexes;
    }

    public function getObjectsInIndex($index)
    {
        # code...
    }

    public function getObjectsInIndexes(array $indexes)
    {
        # code...
    }

    public function flushIndex($index)
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        $indexKeys = $this->getIndexKeys($index);
        $this->redis->del($indexKeys);
    }

    public function flushAllIndexes()
    {
        $groupKeys = $this->getGroupKeys();
        $this->redis->del($groupKeys);
    }

    protected function getGroupKeys()
    {
        $groupKeyPattern = $this->qualifyCacheKey('*');
        return $this->getKeysForPattern($groupKeyPattern);
    }

    protected function getIndexKeys($index)
    {
        $indexKeyPattern = $this->qualifyCacheKey($index) . ':*';
        return $this->getKeysForPattern($indexKeyPattern);
    }

    protected function getKeysForPattern($pattern)
    {
        $keys = [];
        $iterator = null;
        while ($iKeys = $this->redis->scan($iterator, $pattern)) {
            $keys = array_merge($keys, $iKeys);
        }
        return $keys;
    }

    protected function qualifyCacheKey($key)
    {
        return "{$this->keyPrefix}:{$key}";
    }


}

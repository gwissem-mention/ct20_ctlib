<?php
namespace CTLib\Component\FilteredObjectIndex;

use Celltrak\RedisBundle\Component\Client\CellTrakRedis;
use CTLib\Component\Monolog\Logger;
use CTLib\Util\GroupedFilterSet;


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

    public function addObjectToIndex($index, $objectId, array $filters = [])
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        $this->redis->multi(\Redis::PIPELINE);
        $this->addObjectToIndexSets($index, $objectId, $filters);
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
        $this->removeObjectFromSets($indexKeys, $objectId);
        $results = $this->redis->exec();

        return in_array(1, $results);
    }

    public function removeObjectFromAllIndexes($objectId)
    {
        $groupKeys = $this->getGroupKeys();

        $this->redis->multi(\Redis::PIPELINE);
        $this->removeObjectFromSets($groupKeys, $objectId);
        $results = $this->redis->exec();

        $results = array_combine($groupKeys, $results);
        return $this->getRemovedFromIndexes($results);
    }

    public function moveObjectToIndex($index, $objectId, array $filters = [])
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        // Remove object from all group indexes and add to specified one within
        // a single Redis pipeline.

        // Retrieve all set keys for this index group.
        $groupKeys = $this->getGroupKeys();

        $this->redis->multi(\Redis::PIPELINE);
        $this->removeObjectFromSets($groupKeys, $objectId);
        $this->addObjectToIndexSets($index, $objectId, $filters);
        $results = $this->redis->exec();

        // To determine which indexes object was removed from, slice results to
        // just get through Redis sRem calls.
        $removalResults = array_slice($results, 0, count($groupKeys));
        $removalResults = array_combine($groupKeys, $removalResults);
        return $this->getRemovedFromIndexes($removalResults);
    }

    public function getObjectsInIndex($index, GroupedFilterSet $filterSet = null)
    {
        if (!$filterSet || count($filterSet) == 0) {
            // Not filtering; simply return objects in global.
            return $this->getObjectsInIndexGlobal($index);
        }

        if (count($filterSet) == 1) {
            // Filtering for a single filter group only. Run simple UNION.
            $filters = current($filterSet);
            return $this->getObjectsInIndexFilters($index, $filters);
        }

        // Filtering with multiple filter groups. Run UNIONs and INTERSECTIONs.
        return $this->getObjectsInIndexGroupedFilters($index, $filterSet);
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

    protected function addObjectToIndexSets(
        $index,
        $objectId,
        array $filters = []
    ) {
        // NOTE: This is a helper method used to make the set addition logic
        // DRY. It's intentially not initializing a Redis pipeline/transaction
        // because it knows its callers will need to handle based on their
        // specific use case.

        // Add to global set.
        $setKey = $this->qualifyIndexGlobalKey($index);
        $this->redis->sAdd($setKey, $objectId);
        $setCount++;

        // Add to each filter set.
        foreach ($filters as $filter) {
            $setKey = $this->qualifyIndexFilterKey($index, $filter);
            $this->redis->sAdd($setKey, $objectId);
        }
    }

    protected function removeObjectFromSets(array $setKeys, $objectId)
    {
        // NOTE: This is a helper method used to make the set removal logic DRY.
        // It's intentionally not initializing a Redis pipeline/transaction
        // because it knows its callers will need to handle based on their
        // specific use case.

        foreach ($setKeys as $setKey) {
            $this->redis->sRem($setKey, $objectId);
        }
    }

    protected function getRemovedFromIndexes(array $removalResults)
    {
        $removedFromIndexes = [];

        foreach ($this->indexes as $index) {
            $indexGlobalKey = $this->qualifyIndexGlobalKey($index);
            if (isset($results[$indexGlobalKey]) && $results[$indexGlobalKey]) {
                $removedFromIndexes[] = $index;
            }
        }
        return $removedFromIndexes;
    }

    protected function getObjectsInIndexGlobal($index)
    {
        $objectIds = [];
        $indexGlobalKey = $this->qualifyIndexGlobalKey($index);
        $iterator = null;

        while ($iObjectIds = $this->redis->sScan($iterator, $indexGlobalKey)) {
            $objectIds = array_merge($objectIds, $iObjectIds);
        }
        return $objectIds;
    }

    protected function getObjectsInIndexFilters($index, array $filters)
    {
        $indexKeys = $this->qualifyIndexFilterKeys($index, $filters);
        return $this->redis->sUnion(...$indexKeys);
    }

    protected function getObjectsInIndexGroupedFilters(
        $index,
        GroupedFilterSet $filterSet
    ) {
        $intersectionKeys = [];
        $tmpUnionKeys = [];

        $this->redis->multi(\Redis::PIPELINE);

        foreach ($filterSet as $filterGroupId => $filters) {
            if (count($filters) == 1) {
                // Since only a single filter for this filter group, we just
                // need to record this index key for the ultimate INTERSECTION.
                $indexKey = $this->qualifyIndexFilterKey($index, $filters[0]);
                $intersectionKeys[] = $indexKey;
            } else {
                // Since multiple, filters for same filter group, we need to
                // store UNION of all objects in filters in temporary key.
                $tmpUnionKey = $this->generateRandomKey();
                $indexKeys = $this->qualifyIndexFilterKeys($index, $filters);
                $this->redis->sUnionStore($tmpUnionKey, ...$indexKeys);

                $intersectionKeys[] = $tmpUnionKey;
                $tmpUnionKeys[] = $tmpUnionKey;
            }
        }

        $this->redis->sInter(...$intersectionKeys);
        $this->redis->del($tmpUnionKeys);
        $results = $this->redis->exec();

        $intersectionIndex = count($results) - 2;
        return $results[$intersectionIndex];
    }

    protected function generateRandomKey()
    {
        return $this->qualifyCacheKey(md5(uniqid()));
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

    protected function qualifyIndexGlobalKey($index)
    {
        return $this->qualifyCacheKey("{$index}:global");
    }

    protected function qualifyIndexFilterKey($index, $filter)
    {
        return $this->qualifyCacheKey("{$index}:{$filter}");
    }

    protected function qualifyIndexFilterKeys($index, array $filters)
    {
        return array_map(
            function($filter) use ($index) {
                return $this->qualifyIndexFilterKey($index, $filter);
            },
            $filters
        );
    }

    protected function qualifyCacheKey($key)
    {
        return "{$this->keyPrefix}:{$key}";
    }


}

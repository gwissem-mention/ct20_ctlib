<?php
namespace CTLib\Component\FilteredObjectIndex;

use Celltrak\RedisBundle\Component\Client\CellTrakRedis;
use CTLib\Component\Monolog\Logger;
use CTLib\Util\GroupedFilterSet;

/**
 * Manages group of in-memory filtered object indexes. Indexes are used to
 * quickly calculate object -> filter assignments.
 *
 * @author Mike Turoff
 */
class FilteredObjectIndexGroup
{

    /**
     * Namespace for storing index keys in Redis.
     * @var string
     */
    protected $keyNamespace;

    /**
     * Redis client.
     * @var CellTrakRedis
     */
    protected $redis;

    /**
     * Logger
     * @var Logger
     */
    protected $logger;

    /**
     * Set of indexes in this group.
     * @var array
     */
    protected $indexes;


    /**
     * @param string $keyNamespace
     * @param CellTrakRedis $redis
     * @param Logger $logger
     */
    public function __construct(
        $keyNamespace,
        CellTrakRedis $redis,
        Logger $logger
    ) {
        $this->keyNamespace = $keyNamespace;
        $this->redis        = $redis;
        $this->logger       = $logger;
        $this->indexes      = [];
    }

    /**
     * Adds index into group.
     * @param string $index
     * @return void
     */
    public function addIndex($index)
    {
        if (!$this->hasIndex($index)) {
            $this->indexes[] = $index;
        }
    }

    /**
     * Indicates whether index belongs in group.
     * @param string $index
     * @return boolean
     */
    public function hasIndex($index)
    {
        return in_array($index, $this->indexes);
    }

    /**
     * Returns set of indexes in group.
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * Adds object to index.
     * @param string $index
     * @param mixed $objectId
     * @param array $filters    Set of filters assigned to object.
     * @return boolean  Indicates whether object was added.
     */
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

    /**
     * Removes object from index.
     * @param string $index
     * @param mixed $objectId
     * @return boolean  Indicates whether object was removed.
     */
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

    /**
     * Removes object from all group indexes.
     * @param mixed $objectId
     * @return array    Set of indexes where object was removed.
     */
    public function removeObjectFromAllIndexes($objectId)
    {
        $groupKeys = $this->getGroupKeys();

        $this->redis->multi(\Redis::PIPELINE);
        $this->removeObjectFromSets($groupKeys, $objectId);
        $results = $this->redis->exec();

        $results = array_combine($groupKeys, $results);
        return $this->getRemovedFromIndexes($results);
    }

    /**
     * Moves object to index ensuring it only exists in target index.
     * @param string $index
     * @param mixed $objectId
     * @param array $filters    Set of filters assigned to object.
     * @return array    Set of indexes where object was removed.
     */
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

    /**
     * Returns objects in index.
     * @param string $index
     * @param GroupedFilterSet $filterSet   Retrieves objects that only match
     *                                      specified groups + filters.
     * @return array  [$objectId, ...]
     */
    public function getObjectsInIndex($index, GroupedFilterSet $filterSet = null)
    {
        if (!$filterSet || count($filterSet) == 0) {
            // Not filtering; simply return objects in global.
            return $this->getObjectsInIndexGlobal($index);
        }

        if (count($filterSet) == 1) {
            // Filtering for a single filter group only. Run simple UNION.
            $filters = $filterSet->current();
            return $this->getObjectsInIndexFilters($index, $filters);
        }

        // Filtering with multiple filter groups. Run UNIONs and INTERSECTIONs.
        return $this->getObjectsInIndexGroupedFilters($index, $filterSet);
    }

    /**
     * Flush all objects out of index.
     * @param string $index
     * @return void
     */
    public function flushIndex($index)
    {
        if (!$this->hasIndex($index)) {
            throw new \InvalidArgumentException("Index '{$index}' is not in group");
        }

        $indexKeys = $this->getIndexKeys($index);
        $this->redis->del($indexKeys);
    }

    /**
     * Flushes all objects from all group indexes.
     * @return void
     */
    public function flushAllIndexes()
    {
        $groupKeys = $this->getGroupKeys();
        $this->redis->del($groupKeys);
    }

    /**
     * Adds object to index Redis sets.
     * @param string $index
     * @param mixed $objectId
     * @param array $filters
     * @return void
     */
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

        // Add to each filter set.
        foreach ($filters as $filter) {
            $setKey = $this->qualifyIndexFilterKey($index, $filter);
            $this->redis->sAdd($setKey, $objectId);
        }
    }

    /**
     * Removes object from specified sets.
     * @param array $setKeys
     * @param mixed $objectId
     * @return void
     */
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

    /**
     * Returns set of indexes that had object removed based on Redis pipeline
     * results.
     * @param array $removalResults
     * @return array  Set of indexes where object was removed.
     */
    protected function getRemovedFromIndexes(array $removalResults)
    {
        $removedFromIndexes = [];

        foreach ($this->indexes as $index) {
            $indexGlobalKey = $this->qualifyIndexGlobalKey($index);
            if (isset($removalResults[$indexGlobalKey])
                && $removalResults[$indexGlobalKey]) {
                $removedFromIndexes[] = $index;
            }
        }
        return $removedFromIndexes;
    }

    /**
     * Returns objects in index's "global" filter.
     * @param string $index
     * @return array  [$objectId, ...]
     */
    protected function getObjectsInIndexGlobal($index)
    {
        $objectIds = [];
        $indexGlobalKey = $this->qualifyIndexGlobalKey($index);
        $iterator = null;

        while ($iObjectIds = $this->redis->sScan($indexGlobalKey, $iterator)) {
            $objectIds = array_merge($objectIds, $iObjectIds);
        }
        return $objectIds;
    }

    /**
     * Returns objects in any of the specified index's filters.
     * @param string $index
     * @param array $filters
     * @return array   [$objectId, ...]
     */
    protected function getObjectsInIndexFilters($index, array $filters)
    {
        $indexKeys = $this->qualifyIndexFilterKeys($index, $filters);
        return $this->redis->sUnion(...$indexKeys);
    }

    /**
     * Returns objects in index for specified filter set.
     * @param string $index
     * @param GroupedFilterSet $filterSet
     * @return array [$objectId, ...]
     */
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

    /**
     * Generates random Redis key in group's namespace.
     * @return string
     */
    protected function generateRandomKey()
    {
        return $this->qualifyKey(md5(uniqid()));
    }

    /**
     * Returns Redis keys defined for group.
     * @return array
     */
    protected function getGroupKeys()
    {
        $groupKeyPattern = $this->qualifyKey('*');
        return $this->redis->scanForKeys($groupKeyPattern);
    }

    /**
     * Returns Redis keys defined for index.
     * @param string $index
     * @return array
     */
    protected function getIndexKeys($index)
    {
        $indexKeyPattern = $this->qualifyKey($index) . ':*';
        return $this->redis->scanForKeys($indexKeyPattern);
    }

    /**
     * Returns fully qualified index "global" filter key.
     * @param string $index
     * @return string
     */
    protected function qualifyIndexGlobalKey($index)
    {
        return $this->qualifyKey("{$index}:global");
    }

    /**
     * Returns fully qualified index filter key.
     * @param string $index
     * @param string $filter
     * @return string
     */
    protected function qualifyIndexFilterKey($index, $filter)
    {
        return $this->qualifyKey("{$index}:{$filter}");
    }

    /**
     * Returns fully qualified index filter keys.
     * @param string $index
     * @param array $filters
     * @return array
     */
    protected function qualifyIndexFilterKeys($index, array $filters)
    {
        return array_map(
            function($filter) use ($index) {
                return $this->qualifyIndexFilterKey($index, $filter);
            },
            $filters
        );
    }

    /**
     * Fully qualifies Redis key.
     * @param string $key
     * @return string
     */
    protected function qualifyKey($key)
    {
        return "{$this->keyNamespace}:{$key}";
    }


}

<?php
namespace CTLib\Helper;

use CTLib\Component\QueryMetaMap\QueryMetaMap;


class QueryMetaMapCache
{
    protected $cache;
    protected $maps;

    public function __construct($cache)
    {
        $this->cache    = $cache;
        $this->maps     = array();
    }


    /**
     * Returns meta map for passed $queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @return QueryMetaMap
     */
    public function getMap($queryBuilder)
    {
        $queryBuilderId = $this->formatQueryBuilderId($queryBuilder);
        $this->loadMap($queryBuilder, $queryBuilderId);
        return $this->maps[$queryBuilderId];
    }

    /**
     * Loads meta map from cache or builds fresh instance.
     *
     * @param QueryBuilder $queryBuilder
     * @param string $queryBuilderId
     *
     * @return void
     */
    protected function loadMap($queryBuilder, $queryBuilderId)
    {
        if (isset($this->maps[$queryBuilderId])) {
            return;
        }

        // Try getting from cache.
        $cacheKey       = "queryMetaMap.$queryBuilderId";
        $queryMetadata  = $this->cache->get($cacheKey);

        if ($queryMetadata) {
            $this->maps[$queryBuilderId] = $queryMetadata;
            return;
        }

        // Othewise create new map and store in cache.
        $this->maps[$queryBuilderId] = QueryMetaMap::create($queryBuilder);
        $this->cache->set($cacheKey, $this->maps[$queryBuilderId]);
        return;
    }

    /**
     * Formats unique identifier for $queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @return string
     */
    protected function formatQueryBuilderId($queryBuilder)
    {
        if ($joins = $queryBuilder->getDqlPart('join')) {
            $joins = join(' ', current($joins));
        } else {
            $joins = '';
        }

        return md5(
            (string) current($queryBuilder->getDqlPart('select')) .
            (string) current($queryBuilder->getDqlPart('from')) .
            $joins
        );
    }


}
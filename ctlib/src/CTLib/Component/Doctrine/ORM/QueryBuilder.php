<?php
namespace CTLib\Component\Doctrine\ORM;

use CTLib\Helper\EntityMetaHelper,
    CTLib\Util\Util;

/**
 * CellTrak customer QueryBuilder class.
 */
class QueryBuilder extends \Doctrine\ORM\QueryBuilder
{
    /**
     * @var array
     */
    protected $effectiveWhere = array();

    protected $isEffectiveWhereProcessing = false;
    
    /**
     * @var array
     */
    protected $permissionAliases = null;

    /**
     * Returns QueryMetaMap for this QueryBuilder.
     *
     * @return QueryMetaMap
     */
    public function getQueryMetaMap()
    {
        return $this->getEntityManager()->getQueryMetaMap($this);
    }

    /**
     * Adds effective WHERE clause for specified entity $alias.
     *
     * @param string  $alias         Entity alias used in this QueryBuilder.
     * @param integer $effectiveTime If NULL, will use current time.
     *
     * @return QueryBuilder         Returns this QueryBuilder instance.
     */
    public function andEffectiveWhere($alias, $effectiveTime=null)
    {
        if (in_array($alias, $this->effectiveWhere)) {
            throw new \Exception("Effective where already added for alias: $alias");
        }

        if ($effectiveTime) {
            if (! is_int($effectiveTime) || $effectiveTime < 0) {
                throw new \Exception('$effectiveTime must be an unsigned integer');
            }
        } else {
            $effectiveTime = time();
        }

        $this->effectiveWhere[$alias] = array(
            "effectiveTime"     => $effectiveTime,
            "effectiveWhereDQL" => null,
            "isLeftJoin"        => false
        );
    
        return $this;
    }


    private function buildDQLForEffectiveWhere()
    {
        $this->isEffectiveWhereProcessing = true;
        
        foreach ($this->effectiveWhere as $alias => $where)
        {
            if ($where["effectiveWhereDQL"]) { continue; }

            $entity             = $this->getQueryMetaMap()->mustGetEntity($alias);
            $logicalIdFields    = $this->getEntityManager()
                ->getEntityMetaHelper()
                ->getLogicalIdentifierFieldNames($entity->name);
            $effectiveAlias     = $alias . "_EF";
            $logicalIdCriteria  = array_map(
                function ($idColumn) use ($alias, $effectiveAlias) {
                    return "{$alias}.{$idColumn} = {$effectiveAlias}.{$idColumn}";
                },
                $logicalIdFields
            );
            $logicalIdWhereDql  = join(' AND ', $logicalIdCriteria);
            $effectiveWhereDql  = "{$alias}.effectiveTime = (
                SELECT MAX({$effectiveAlias}.effectiveTime)
                FROM {$entity->name} {$effectiveAlias}
                WHERE {$logicalIdWhereDql} AND
                    {$effectiveAlias}.effectiveTime <= {$where["effectiveTime"]})";
            
            $this->effectiveWhere[$alias]["effectiveWhereDQL"] = $effectiveWhereDql;

            if ($this->effectiveWhere[$alias]["isLeftJoin"]) {
                $this->andWhere($this->expr()->orx(
                    $effectiveWhereDql,
                    "{$alias}.effectiveTime IS NULL"
                ));
            }
            else {
                $this->andWhere($effectiveWhereDql);
            }
        }
        
        $this->isEffectiveWhereProcessing = false;
    }

    public function getDQL()
    {
        if (!$this->isEffectiveWhereProcessing) {
            $this->buildDQLForEffectiveWhere();
        }
        return parent::getDQL();
    }

    /**
     * Set the permission alias.
     *
     * NOTE: Value stored is compatible with QueryBuuilder::getRootEntities().
     *
     * @param string $permissionAlias
     *
     * @return QueryBuilder
     */
    public function setPermissionAlias($permissionAlias)
    {
        $this->permissionAliases = (array) $permissionAlias;
        return $this;
    }

    /**
     * Get the aliases for which we want to test permissions.
     *
     * Will return root aliases if not set.
     *
     * @return array
     */
    public function getPermissionAliases()
    {
        return $this->permissionAliases ?: $this->getRootAliases();
    }

    /**
     * Gets the entities to test permission of in the query.
     *
     * NOTE: This is plural because I'm keeping it consistent with getRootEntities.
     *
     * @return array $permissionEntities
     */
    public function getPermissionEntities()
    {
        $entities = array();
        $aliases = $this->getPermissionAliases();
        $queryMetaMap = $this->getQueryMetaMap();

        foreach ($aliases as $alias) {
            $entity = $queryMetaMap->mustGetEntity($alias);
            $entities[] = $entity->name;
        }

        return $entities;
    }
    
    /**
     * reset all query builder parts
     *
     * @return this $this
     *
     */
    public function reset()
    {
        $this->resetDQLParts();
        $this->setMaxResults(null);
        $this->setFirstResult(null);
        return $this;
    }

    /**
     * add center nearby search parts to query builder, this helps
     * search all recodes that are some radius distance away from
     * given center point. the search area is a circle.
     *
     * @param float $centerLat latitude of center point
     * @param float $centerLng longitude of center point
     * @param string $latField name of searched latitude field
     * @param string $lngField name of searched longitude field
     * @param float $radius radius away from center point
     * @param string $distanceUnit distance unit (kilometer / mile)
     * @param string $distanceField name of distance result field in select
     * @return array all results that are $radius distance unit away from center point
     *
     */    
    public function addNearbySearch($centerLat, $centerLng, $latField, $lngField, $radius, $distanceUnit, $distanceField = "distance")
    {
        $expr = $this->expr();
        list($latDelta, $lngDelta) = Util::getLatLngDelta($centerLat, $centerLat, $radius, $distanceUnit);

        $latLowerBound = $centerLat - $latDelta;
        $latUpperBound = $centerLat + $latDelta;
        $lngLowerBound = $centerLng - $lngDelta;
        $lngUpperBound = $centerLng + $lngDelta;

        $this
            ->addSelect(
                "ArcDistance(
                    {$centerLat}, 
                    {$centerLng}, 
                    {$latField}, 
                    {$lngField}, 
                    {$distanceUnit})
                AS {$distanceField}")
            ->andHaving($expr->lte($distanceField, $radius))
            ->andWhere($expr->between($latField, $latLowerBound, $latUpperBound))
            ->andWhere($expr->between($lngField, $lngLowerBound, $lngUpperBound));
        return $this;
    }
    
    /**
     * effective join to another table
     *
     * @param string $join The relationship to join
     * @param string $alias The alias of the join
     * @param int    $effectiveTime The current effective time
     * @param string $conditionType The condition type constant. Either ON or WITH.
     * @param string $condition The condition for the join
     * @param string $indexBy The index for the join
     * @return QueryBuilder This QueryBuilder instance.
     *
     */    
    public function effectiveJoin($join, $alias, $effectiveTime=null, $conditionType = null, $condition = null, $indexBy = null)
    {
        $this->join($join, $alias, $conditionType, $condition, $indexBy)
            ->andEffectiveWhere($alias, $effectiveTime);
        return $this;
    }

    /**
     * effective left join to another table
     *
     * @param string $join The relationship to join
     * @param string $alias The alias of the join
     * @param int    $effectiveTime The current effective time
     * @param string $conditionType The condition type constant. Either ON or WITH.
     * @param string $condition The condition for the join
     * @param string $indexBy The index for the join
     * @return QueryBuilder This QueryBuilder instance.
     *
     */    
    public function effectiveLeftJoin($join, $alias, $effectiveTime=null, $conditionType = null, $condition = null, $indexBy = null)
    {
        $this->leftJoin($join, $alias, $conditionType, $condition, $indexBy)
            ->andEffectiveWhere($alias, $effectiveTime);
        $this->effectiveWhere[$alias]["isLeftJoin"] = true;
        return $this;
    }
}

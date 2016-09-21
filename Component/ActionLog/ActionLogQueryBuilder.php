<?php

namespace CTLib\Component\ActionLog;

use CTLib\Component\DataAccess\DataProvider;
use CTLib\Component\DataAccess\JsonDataOutput;
use CTLib\Component\DataAccess\CtApiDocumentDataAccess;

/**
 * Class ActionLogQueryBuilder
 * Helper class to construct neccessary data to query
 * Action Logs.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class ActionLogQueryBuilder
{
    const SORT_ASC  = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * @var CtApiDocumentDataAccess
     */
    protected $dataAccess;

    /**
     * @var array
     */
    protected $queryFields;

    /**
     * @var array
     */
    protected $queryFilters;

    /**
     * @var string
     */
    protected $sortOrder;


    /**
     * @param CtApiCaller $ctApiCaller
     * @param string $uri
     */
    public function __construct($ctApiCaller, $uri)
    {
        $this->dataAccess = new CtApiDocumentDataAccess(
            $ctApiCaller,
            $uri
        );

        $this->queryFields  = [];
        $this->queryFilters = [];
        $this->sortOrder    = self::SORT_ASC;
    }

    /**
     * @param string $field
     *
     * @return ActionLogQueryBuilder
     */
    public function addField($field)
    {
        $this->queryFields[] = $field;
        return $this;
    }

    /**
     * @param int $acitonCode
     *
     * @return ActionLogQueryBuilder
     */
    public function addActionCodeFilter($actionCode)
    {
        $this->queryFilters['actionCode'][] = $actionCode;
        return $this;
    }

    /**
     * @param string $source
     *
     * @return ActionLogQueryBuilder
     */
    public function addSourceFilter($source)
    {
        $this->queryFilters['source'][] = $source;
        return $this;
    }

    /**
     * @param int $memberId
     *
     * @return ActionLogQueryBuilder
     */
    public function setMemberIdFilter($memberId)
    {
        $this->queryFilters['memberId'] = $memberId;
        return $this;
    }

    /**
     * @param string $entityClass
     * @param int $entityId
     *
     * @return ActionLogQueryBuilder
     */
    public function setEntityFilter($entityClass, $entityId)
    {
        $this->queryFilters['affectedEntity.class'] = $entityClass;
        $this->queryFilters['affectedEntity.id'] = $entityId;
        return $this;
    }

    /**
     * @param int $fromTimestamp
     * @param int $toTimestamp
     *
     * @return ActionLogQueryBuilder
     */
    public function setDateRangeFilter($fromTimestamp, $toTimestamp)
    {
        $dateRange = [];

        if ($fromTimestamp) {
            $dateRange[] = $fromTimestamp;
        }
        if ($toTimestamp) {
            $dateRange[] = $toTimestamp;
        }
        if ($dateRange) {
            $this->queryFilters['dateRange'] = $dateRange;
        }
        return $this;
    }

    /**
     * @param string $sortOrder
     *
     * @return ActionLogQueryBuilder
     */
    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * @param int $maxResults
     *
     * @return ActionLogQueryBuilder
     */
    public function setMaxResults($maxResults)
    {
        $this->dataAccess->setMaxResults($maxResults);
        return $this;
    }

    /**
     * @param int $offset
     *
     * @return ActionLogQueryBuilder
     */
    public function setOffset($offset)
    {
        $this->dataAccess->setOffset($offset);
        return $this;
    }

    /**
     * Make the actual request to the API, and return the results.
     *
     * @return array
     */
    public function getResult()
    {
        $out = new JsonDataOutput();
        $dp = new DataProvider();

        foreach ($this->queryFields as $field) {
            $this->dataAccess->addField($field);
        }

        $this->dataAccess->addSort('addedOn', $this->sortOrder);

        foreach ($this->queryFilters as $field => $value) {
            if (is_array($value)) {
                if ($field == 'dateRange') {
                    $this->dataAccess->addFilter('addedOn', $value[0], 'gte');
                    $this->dataAccess->addFilter('addedOn', $value[1], 'lte');
                } else {
                    $this->dataAccess->addFilter($field, $value, 'in');
                }
            } else {
                $this->dataAccess->addFilter($field, $value);
            }
        }

        $dp->addFields($this->dataAccess->getFields());

        $results = $dp->getResult($this->dataAccess, $out);

        return json_decode($results, true);
    }
}

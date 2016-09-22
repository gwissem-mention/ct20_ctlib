<?php

namespace CTLib\Component\ActionLog;

use CTLib\Util\Util;

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
     * @var EntityMetaHelper
     */
    protected $entityMetaHelper;

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
     * @param EntityMetaHelper $entityMetaHelper
     * @param CtApiDocumentDataAccess $dataAccess
     */
    public function __construct($dataAccess, $entityMetaHelper)
    {
        $this->dataAccess       = $dataAccess;
        $this->entityMetaHelper = $entityMetaHelper;
        $this->queryFields      = [];
        $this->queryFilters     = [];
        $this->sortOrder        = self::SORT_ASC;

        $this->addDefaultFields();
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
     * @param BaseEntity $entityClass
     *
     * @return ActionLogQueryBuilder
     */
    public function setEntityFilter($entity)
    {
        $className = $this->entityMetaHelper->getShortClassName($entity);

        $entityIds = $this
            ->entityMetaHelper
            ->getLogicalIdentifierFieldNames($entity);

        if (count($entityIds) > 1) {
            throw new \RuntimeException('Multi-id entities not supported');
        }

        $entityId = $entity->{"get{$entityIds[0]}"}();

        $this->queryFilters['affectedEntity.class'] = $className;
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

        return $this->dataAccess->getData();
    }

    /**
     * @return ActionLogQueryBuilder
     */
    protected function addDefaultFields()
    {
        $this->dataAccess
            ->addField('actionCode')
            ->addField('memberId')
            ->addField('affectedEntity')
            ->addField('source')
            ->addField('comment')
            ->addField('addedOn')
            ->addField('addedOnWeek')
            ->addField('addedOn');

        return $this;
    }
}

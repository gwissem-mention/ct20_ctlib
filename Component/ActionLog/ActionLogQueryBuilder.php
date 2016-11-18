<?php

namespace CTLib\Component\ActionLog;

use CTLib\Util\Util;
use CTLib\Component\Doctrine\ORM\EntityManager;
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
     * @var EntityManager
     */
    protected $entityManager;

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
     * @param CtApiDocumentDataAccess $dataAccess
     * @param EntityManager $entityManager
     */
    public function __construct(
        CtApiDocumentDataAccess $dataAccess,
        EntityManager $entityManager
    ) {
        $this->dataAccess       = $dataAccess;
        $this->entityManager    = $entityManager;
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
     * @param array $actionCodes
     *
     * @return ActionLogQueryBuilder
     */
    public function setActionCodeFilter(array $actionCodes)
    {
        $this->queryFilters['action'] = $actionCodes;
        return $this;
    }

    /**
     * @param array $sources
     *
     * @return ActionLogQueryBuilder
     */
    public function setSourceFilter(array $sources)
    {
        $this->queryFilters['source'] = $sources;
        return $this;
    }

    /**
     * @param array $memberIds
     *
     * @return ActionLogQueryBuilder
     */
    public function setMemberIdFilter($memberIds)
    {
        $this->queryFilters['user.id'] = $memberIds;
        return $this;
    }

    /**
     * @param array $memberTypeIds
     *
     * @return ActionLogQueryBuilder
     */
    public function setMemberTypeFilter($memberTypeIds)
    {
        if (!isset($this->queryFilters['extra'])) {
            $this->queryFilters['extra'] = [];
        }
        $this->queryFilters['extra']['memberTypeId'] = $memberTypeIds;
        return $this;
    }

    /**
     * @param array $roles
     *
     * @return ActionLogQueryBuilder
     */
    public function setRoleFilter($roles)
    {
        $this->queryFilters['user.role'] = $roles;
        return $this;
    }

    /**
     * @param BaseEntity $entity
     *
     * @return ActionLogQueryBuilder
     */
    public function setEntityFilter($entity)
    {
        $this->queryFilters['parentEntity.class'] =
            Util::shortClassName($entity);

        if (method_exists($entity, 'getEntityId')) {
            $this->queryFilters['parentEntity.id'] = $entity->getEntityId();
        } else {
            $this->queryFilters['parentEntity.id'] = $this
                ->entityManager
                ->getEntityId($entity);
        }

        return $this;
    }

    /**
     * @param array $filterIds
     *
     * @return ActionLogQueryBuilder
     */
    public function setFiltersFilter(array $filterIds)
    {
        $this->queryFilters['parentEntity.filters'] = $filterIds;
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
                // Here we are forcing parentEntity.id value to be of type string.
                // We do this because this field's value may be numeric or
                // alphanumeric. If it is numeric, mongo will not find the value, as
                // it will default to looking for a numeric value, but we store this
                // field value as a string. The value 2 represents the data type
                // 'string' for mongodb.
                if ($field == 'parentEntity.id') {
                    $this->dataAccess->addFilter($field, $value, 'eq');
                } else {
                    $this->dataAccess->addFilter($field, $value);
                }
            }
        }

        return $this->dataAccess->getData();
    }

    /**
     * @return ActionLogQueryBuilder
     */
    protected function addDefaultFields()
    {
        $this->queryFields = [
            'action',
            'user',
            'affectedEntity',
            'parentEntity',
            'source',
            'comment',
            'extra',
            'addedOn',
            'addedOnWeek',
            'addedOn'
        ];
        return $this;
    }
}

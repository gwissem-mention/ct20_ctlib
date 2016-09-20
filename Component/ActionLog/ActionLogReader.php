<?php

namespace CTLib\Component\ActionLog;

use CTLib\Component\DataAccess\DataProvider;
use CTLib\Component\DataAccess\JsonDataOutput;
use CTLib\Component\DataAccess\CtApiDocumentDataAccess;

/**
 * Class ActionLogReader
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class ActionLogReader
{
    const AUDIT_LOG_API_PATH = '/actionLogs';

    const SORT_ASC  = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * @var CtApiDocumentDataAccess
     */
    protected $dataAccess;

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
     */
    public function __construct($ctApiCaller)
    {
        $this->dataAccess = new CtApiDocumentDataAccess(
            $ctApiCaller,
            self::AUDIT_LOG_API_PATH
        );

        $this->queryFilters = [];
        $this->sortOrder    = self::SORT_ASC;
    }

    /**
     * Retrieve ActionLog documents for a specific action
     * from mongo via API.
     *
     * @param int $action
     *
     * @return array
     */
    public function getLogsForAction(
        $action,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $sortOrder     = self::SORT_ASC
    ) {
        $this->sortOrder = $sortOrder;

        $this->queryFilters['actionCode'] = $action;

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

        return $this->getData();
    }

    /**
     * Retrieve ActionLog documents for a given entityId
     * from mongo via API.
     *
     * @param BaseEntity $entity
     * @param int $action
     *
     * @return array
     */
    public function getLogsForEntity(
        $entity,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = self::SORT_ASC
    ) {
        $this->sortOrder = $sortOrder;

        $className = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->{"get{$className}Id"}();

        $this->queryFilters['affectedEntity.id'] = $entityId;
        $this->queryFilters['affectedEntity.class'] = $className;

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

        if ($action) {
            $this->queryFilters['actionCode'] = $action;
        }

        return $this->getData($sortOrder);
    }

    /**
     * Retrieve ActionLog documents for a member
     * from mongo via API.
     *
     * @param int $memberId
     * @param int $action
     *
     * @return array
     */
    public function getLogsForMember(
        $memberId,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = self::SORT_ASC
    ) {
        $this->sortOrder = $sortOrder;

        $this->queryFilters['memberId'] = $memberId;

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

        if ($action) {
            $this->queryFilters['actionCode'] = $action;
        }

        return $this->getData();
    }

    /**
     * Make the actual request to the API, and return the results.
     *
     * @return array
     */
    protected function getData()
    {
        $out = new JsonDataOutput();
        $dp = new DataProvider();

        $this->dataAccess
            ->addField('actionCode')
            ->addField('memberId')
            ->addField('affectedEntity')
            ->addField('source')
            ->addField('comment')
            ->addField('ipAddress')
            ->addField('addedOn');

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

        return $dp->getResult($this->dataAccess, $out);
    }
}

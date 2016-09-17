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

    /**
     * @var CtApiCaller
     */
    protected $ctApiCaller;

    /**
     * @var CtApiDocumentDataAccess
     */
    protected $dataAccess;

    /**
     * @var array
     */
    protected $queryFilters;


    /**
     * @param CtApiCaller $ctApiCaller
     */
    public function __construct($ctApiCaller)
    {
        $this->ctApiCaller = $ctApiCaller;

        $this->dataAccess = new CtApiDocumentDataAccess(
            $this->ctApiCaller,
            self::AUDIT_LOG_API_PATH
        );

        $this->queryFilters = [];
    }

    /**
     * Retrieve ActionLog documents for a specific action
     * from mongo via API.
     *
     * @param int $action
     *
     * @return array
     */
    public function getLogsForAction($action)
    {
        $this->queryFilters['actionCode'] = $action;
        return $this->getData();
    }

    /**
     * Retrieve ActionLog documents for a given entityId
     * from mongo via API.
     *
     * @param int $entityId
     * @param int $action
     *
     * @return array
     */
    public function getLogsForEntity($entityId, $action=null)
    {
        $this->queryFilters['affectedEntityId'] = $entityId;

        if ($action) {
            $this->queryFilters['actionCode'] = $action;
        }

        return $this->getData();
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
    public function getLogsForMember($memberId, $action=null)
    {
        $this->queryFilters['memberId'] = $memberId;

        if ($action) {
            $this->queryFilters['actionCode'] = $action;
        }

        return $this->getData();
    }

    /**
     * @param int $memberId
     *
     * @return ActionLogReader
     */
    public function addMember($memberId)
    {
        $this->queryFilters['memberId'] = $memberId;
        return $this;
    }

    /**
     * @param int $startDate
     * @param int $endDate
     *
     * @return ActionLogReader
     */
    public function addDateRange($startDate, $endDate)
    {
        $this->queryFilters['dateRange'] = [$startDate, $endDate];
        return $this;
    }

    /**
     * @param string $source
     *
     * @return ActionLogReader
     */
    public function addSource($source)
    {
        $this->queryFilters['source'] = $source;
        return $this;
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public function getResults()
    {
        if (!count($this->queryFilters)) {
            throw new \Exception('ActionLogReader::getResults() - filter criteria is required');
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
            ->addField('affectedEntityId')
            ->addField('properties')
            ->addField('source')
            ->addField('comment')
            ->addField('ipAddress')
            ->addField('addedOn');

        foreach ($this->queryFilters as $field => $value) {
            if (is_array($value)) {
                if ($field == 'dateRange') {
                    $dac->addFilter('addedOn', $value[0], 'gte');
                    $dac->addFilter('addedOn', $value[1], 'lte');
                } else {
                    $dac->addFilter($field, $value, 'in');
                }
            } else {
                $dac->addFilter($field, $value);
            }
        }

        $dp->addFields($this->dataAccess->getFields());

        return $dp->getResult($this->dataAccess, $out);
    }
}

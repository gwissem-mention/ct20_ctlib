<?php

namespace CTLib\Component\ActionLog;

/**
 * Class ActionLogReader
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class ActionLogReader
{
    const AUDIT_LOG_API_PATH = '/actionLogs';

    /**
     * @var ActionLogQueryBuilder
     */
    protected $queryBuilder;


    /**
     * @param CtApiCaller $ctApiCaller
     */
    public function __construct($ctApiCaller)
    {
        $this->queryBuilder = new ActionLogQueryBuilder(
            $ctApiCaller,
            self::AUDIT_LOG_API_PATH
        );
    }

    /**
     * Retrieve ActionLog documents for a specific action
     * from mongo via API.
     *
     * @param int $action
     * @param int $fromTimestamp
     * @param int $toTimestamp
     * @param string $sortOrder
     *
     * @return array
     */
    public function getLogsForAction(
        $action,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $this->addDefaultFields()
            ->addActionCodeFilter($action)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSort($sortOrder);
        return $this->queryBuilder->getResult();
    }

    /**
     * Retrieve ActionLog documents for a given entityId
     * from mongo via API.
     *
     * @param BaseEntity $entity
     * @param int $fromTimestamp
     * @param int $toTimestamp
     * @param int $action
     * @param string $sortOrder
     *
     * @return array
     */
    public function getLogsForEntity(
        $entity,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $className = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->{"get{$className}Id"}();

        $this->addDefaultFields()
            ->setEntityFilter($className, $entityId)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSort($sortOrder);

        if ($action) {
            $this->queryBuilder->addActionCodeFilter($action);
        }

        return $this->queryBuilder->getResult();
    }

    /**
     * Retrieve ActionLog documents for a member
     * from mongo via API.
     *
     * @param int $memberId
     * @param int $fromTimestamp
     * @param int $toTimestamp
     * @param int $action
     * @param string $sortOrder
     *
     * @return array
     */
    public function getLogsForMember(
        $memberId,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $this->addDefaultFields()
            ->setMemberIdFilter($memberId)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSort($sortOrder);

        if ($action) {
            $this->queryBuilder->addActionCodeFilter($action);
        }

        return $this->queryBuilder->getResult();
    }

    /**
     * Retrieve ActionLog documents for a given entityId
     * from mongo via API.
     *
     * @param BaseEntity $entity
     * @param int $fromTimestamp
     * @param int $toTimestamp
     * @param int $action
     * @param string $sortOrder
     *
     * @return array
     */
    public function getEntityLogsForMember(
        $entity,
        $memberId,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $className = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->{"get{$className}Id"}();

        $this->addDefaultFields()
            ->addEntityFilter($className, $entityId)
            ->setMemberIdFilter($memberId)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSort($sortOrder);

        if ($action) {
            $this->queryBuilder->addActionCodeFilter($action);
        }

        return $this->queryBuilder->getResult();
    }

    protected function addDefaultFields()
    {
        $this->queryBuilder
            ->addField('actionCode')
            ->addField('memberId')
            ->addField('affectedEntity')
            ->addField('source')
            ->addField('comment')
            ->addField('addedOn')
            ->addField('addedOnWeek')
            ->addField('addedOn');

        return $this->queryBuilder;
    }
}

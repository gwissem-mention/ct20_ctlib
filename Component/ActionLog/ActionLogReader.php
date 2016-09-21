<?php

namespace CTLib\Component\ActionLog;

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
     * @var CtApiDocumentDataAccess
     */
    protected $dataAccess;


    /**
     * @param CtApiCaller $ctApiCaller
     */
    public function __construct($ctApiCaller)
    {
        $this->dataAccess = new CtApiDocumentDataAccess(
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
    public function createActionLogQueryBuilder(
        $action,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        return $this
            ->createQueryBuilder()
            ->addActionCodeFilter($action)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSortOrder($sortOrder);
            ->getResult();
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
    public function createEntityLogQueryBuilder(
        $entity,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $qb = $this->createQueryBuilder();
        $qb->setEntityFilter($entity)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSortOrder($sortOrder);

        if ($action) {
            $qb->addActionCodeFilter($action);
        }

        return $qb->getResult();
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
    public function createMemberLogQueryBuilder(
        $memberId,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $qb = $this->createQueryBuilder();
        $qb->setMemberIdFilter($memberId)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSortOrder($sortOrder);

        if ($action) {
            $qb->addActionCodeFilter($action);
        }

        return $qb->getResult();
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
    public function createEntityLogsForMemberQueryBuilder(
        $entity,
        $memberId,
        $fromTimestamp = null,
        $toTimestamp   = null,
        $action        = null,
        $sortOrder     = ActionLogQueryBuilder::SORT_ASC
    ) {
        $qb = $this->createQueryBuilder();
        $qb->addEntityFilter($entity)
            ->setMemberIdFilter($memberId)
            ->setDateRangeFilter($fromTimestamp, $toTimestamp)
            ->setSortOrder($sortOrder);

        if ($action) {
            $qb->addActionCodeFilter($action);
        }

        return $qb->getResult();
    }

    /**
     * @return ActionLogQueryBuilder
     */
    protected function createQueryBuilder()
    {
        return new ActionLogQueryBuilder(
            $this->dataAccess,
            self::AUDIT_LOG_API_PATH
        );
    }
}

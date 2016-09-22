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
     * @var EntityMetaHelper
     */
    protected $entityMetaHelper;


    /**
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     */
    public function __construct($entityManager, $ctApiCaller)
    {
        $this->entityMetaHelper = $entityManager->getEntityMetaHelper();

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
     *
     * @return ActionLogQueryBuilder
     */
    public function createActionLogQueryBuilder($action)
    {
        return $this
            ->createQueryBuilder()
            ->addActionCodeFilter($action);
    }

    /**
     * Retrieve ActionLog documents for a given entityId
     * from mongo via API.
     *
     * @param BaseEntity $entity
     *
     * @return ActionLogQueryBuilder
     */
    public function createEntityLogQueryBuilder($entity)
    {
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogReader::createEntityLogQueryBuilder requires an entity passed as an argument');
        }

        return $this
            ->createQueryBuilder()
            ->setEntityFilter($entity);
    }

    /**
     * Retrieve ActionLog documents for a member
     * from mongo via API.
     *
     * @param int $memberId
     *
     * @return ActionLogQueryBuilder
     */
    public function createMemberLogQueryBuilder($memberId)
    {
        return $this
            ->createQueryBuilder()
            ->setMemberIdFilter($memberId);
    }

    /**
     * Retrieve ActionLog documents for a given entityId
     * from mongo via API.
     *
     * @param BaseEntity $entity
     * @param int $memberId
     *
     * @return ActionLogQueryBuilder
     */
    public function createEntityLogsForMemberQueryBuilder($entity, $memberId)
    {
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogReader::createEntityLogsForMemberQueryBuilder requires an entity passed as an argument');
        }

        return $this
            ->createQueryBuilder()
            ->addEntityFilter($entity)
            ->setMemberIdFilter($memberId);
    }

    /**
     * @return ActionLogQueryBuilder
     */
    public function createQueryBuilder()
    {
        return new ActionLogQueryBuilder(
            $this->dataAccess,
            $this->entityMetaHelper
        );
    }
}

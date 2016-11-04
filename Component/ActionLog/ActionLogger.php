<?php

namespace CTLib\Component\ActionLog;

use CTLib\Util\Util;
use CTLib\Component\Doctrine\ORM\EntityDelta;
use CTLib\Component\EntityFilterCompiler\EntityFilterCompiler;
use CTLib\Component\Doctrine\ORM\EntityManager;
use CTLib\Component\CtApi\CtApiCaller;

/**
 * Class ActionLogger
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class ActionLogger
{
    const SOURCE_OTP       = 'OTP';
    const SOURCE_CTP       = 'CTP';
    const SOURCE_API       = 'API';
    const SOURCE_INTERFACE = 'IFC';
    const SOURCE_HQ        = 'HQ';

    const SYSTEM_MEMBER_ID   = 0;

    const AUDIT_LOG_API_PATH = '/actionLogs';

    /**
     * @var CtApiCaller
     */
    protected $ctApiCaller;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var array
     */
    protected $filterCompilers = [];


    /**
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     * @param string $source
     */
    public function __construct(
        EntityManager $entityManager,
        CtApiCaller $ctApiCaller,
        $source
    ) {
        $this->ctApiCaller      = $ctApiCaller;
        $this->entityManager    = $entityManager;
        $this->source           = $source;
    }

    /**
    * Register a filter compiler with this service.
    *
    * @param EntityFilterCompiler $filterCompiler
    */
    public function registerEntityFilterCompiler(
        EntityFilterCompiler $filterCompiler
    ) {
        $this->filterCompilers[] = $filterCompiler;
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param int $action
     * @param int $memberId
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function add(
        $action,
        $memberId = self::SYSTEM_MEMBER_ID,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::add - action is required');
        }

        $logData = $this->compileActionLogDocument(
            $action,
            $memberId,
            null,
            null,
            null,
            $comment
        );

        $this->addLogEntry($logData);
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param int $action
     * @param $entity
     * @param $parentEntity
     * @param int $memberId
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntity(
        $action,
        $entity,
        $parentEntity,
        $memberId = self::SYSTEM_MEMBER_ID,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - action is required');
        }
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - entity is required');
        }
        if (!$parentEntity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - parentEntity is required');
        }

        $logData = $this->compileActionLogDocument(
            $action,
            $memberId,
            $entity,
            null,
            $parentEntity,
            $comment
        );

        $this->addLogEntry($logData);
    }


    /**
     * Method used to add to action_log when an entity has
     * been 'tracked' via our EntityManager tracking mechanism.
     * Caller should be passing a valid delta value.
     *
     * @param int $action
     * @param $entity
     * @param EntityDelta $delta
     * @param $parentEntity
     * @param int $memberId
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntityDelta(
        $action,
        $entity,
        EntityDelta $delta,
        $parentEntity,
        $memberId = self::SYSTEM_MEMBER_ID,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta - action is required');
        }
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta requires an entity passed as an argument');
        }
        if (!$parentEntity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - parentEntity is required');
        }

        $logData = $this->compileActionLogDocument(
            $action,
            $memberId,
            $entity,
            $delta,
            $parentEntity,
            $comment
        );

        $this->addLogEntry($logData);
    }

    /**
     * Helper method to construct a partial actionLog JSON
     * document.
     *
     * @param int $action
     * @param int $memberId
     * @param $entity
     * @param EntityDelta $delta
     * @param array $childEntities
     * @param string $comment
     *
     * @return string
     */
    protected function compileActionLogDocument(
        $action,
        $memberId,
        $entity = null,
        $delta = null,
        $parentEntity = null,
        $comment = null
    ) {
        $addedOnWeek = Util::getDateWeek(time());

        $doc = [];
        $doc['actionCode']  = $action;
        $doc['memberId']    = $memberId;
        $doc['source']      = $this->source;
        $doc['comment']     = $comment;
        $doc['addedOn']     = time();
        $doc['addedOnWeek'] = $addedOnWeek;

        if ($entity) {
            // If no parentEntity was supplied, we will use the main
            // entity as the parent as well.
            if (!$parentEntity) {
                $parentEntity = $entity;
            }

            $entityIds = $this
                ->entityManager
                ->getEntityId($entity);

            $doc['affectedEntity']['class'] = $this
                ->entityManager
                ->getEntityMetaHelper()
                ->getShortClassName($entity);

            $doc['affectedEntity']['id'] = $entityIds;
            if ($delta) {
                $doc['affectedEntity']['properties'] = $delta;
            }

            // Log parent entity detail.
            $doc['parentEntity']['class'] = $this
                ->entityManager
                ->getEntityMetaHelper()
                ->getShortClassName($parentEntity);

            $entityIds = $this
                ->entityManager
                ->getEntityId($parentEntity);

            $doc['parentEntity']['id'] = current($entityIds);
            $doc['parentEntity']['primaryKey'] = $entityIds;

            $filters = $this->getEntityFilters($parentEntity);
            $doc['parentEntity']['filters'] = $filters;
        }

        return json_encode($doc);
    }

    /**
     * Get all the filters related to the given entity.
     *
     * @param $entity
     *
     * @return array
     */
    protected function getEntityFilters($entity)
    {
        $filters = [];

        foreach ($this->filterCompilers as $filterCompiler) {
            if ($filterCompiler->supportsEntity($entity)) {
                $filters = $filterCompiler->compileFilters($entity);
                break;
            }
        }

        return $filters;
    }

    /**
     * Send the audit log entry to API to be saved
     * in Mongo.
     *
     * @param string $log
     *
     * @return void
     */
    protected function addLogEntry($log)
    {
        $this->ctApiCaller->post(
            self::AUDIT_LOG_API_PATH,
            $log
        );
    }
}

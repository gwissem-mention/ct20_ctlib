<?php

namespace CTLib\Component\ActionLog;

use CTLib\Util\Util;
use CTLib\Component\Doctrine\ORM\EntityDelta;
use CTLib\Component\EntityFilterCompiler\EntityFilterCompiler;


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
     * @var EntityMetaHelper
     */
    protected $entityMetaHelper;

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
        $entityManager,
        $ctApiCaller,
        $source
    ) {
        $this->ctApiCaller      = $ctApiCaller;
        $this->entityMetaHelper = $entityManager->getEntityMetaHelper();
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
            $comment
        );

        $this->addLogEntry($logData);
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param int $action
     * @param BaseEntity $entity
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
        $memberId = self::SYSTEM_MEMBER_ID,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - action is required');
        }
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - entity is required');
        }

        $logData = $this->compileActionLogDocument(
            $action,
            $memberId,
            $entity,
            null,
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
     * @param BaseEntity $entity
     * @param EntityDelta $delta
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
        $memberId = self::SYSTEM_MEMBER_ID,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta - action is required');
        }
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta requires an entity passed as an argument');
        }

        $logData = $this->compileActionLogDocument(
            $action,
            $memberId,
            $entity,
            $delta,
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
     * @param BaseEntity $entity
     * @param EntityDelta $delta
     * @param string $comment
     *
     * @return string
     */
    protected function compileActionLogDocument(
        $action,
        $memberId,
        $entity  = null,
        $delta   = null,
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
            $entityIds = $this
                ->entityMetaHelper
                ->getLogicalIdentifierFieldNames($entity);

            $ids = '';
            foreach ($entityIds as $entityId) {
                $ids .= $entity->{"get{$entityId}"}();
            }

            $doc['affectedEntity']['class'] =
                $this->entityMetaHelper->getShortClassName($entity);
            $doc['affectedEntity']['id'] = $ids;
            if ($delta) {
                $doc['affectedEntity']['properties'][] = $delta;
            }

            $filters = $this->getEntityFilters($entity);
            $doc['affectedEntity']['filters'] = $filters;
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

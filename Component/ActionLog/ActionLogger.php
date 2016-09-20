<?php

namespace CTLib\Component\ActionLog;

use CTLib\Util\Util;

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
     * @var Session
     */
    protected $session;

    /**
     * @var EntityMetaHelper
     */
    protected $entityMetaHelper;

    /**
     * @var string
     */
    protected $source;


    /**
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     * @param Session $session
     * @param string $source
     */
    public function __construct(
        $entityManager,
        $ctApiCaller,
        $session,
        $source
    ) {
        $this->ctApiCaller      = $ctApiCaller;
        $this->session          = $session;
        $this->entityMetaHelper = $entityManager->getEntityMetaHelper();
        $this->source           = $source;
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
        $memberId,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::add - action is required');
        }
        if (!$memberId) {
            throw new \InvalidArgumentException('ActionLogger::add - memberId is required');
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
        $memberId,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - action is required');
        }
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - entity is required');
        }
        if (!$memberId) {
            throw new \InvalidArgumentException('ActionLogger::addForEntity - memberId is required');
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
     * @param string $delta
     * @param int $memberId
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntityDelta(
        $action,
        $memberId,
        $entity,
        $delta,
        $comment = null
    ) {
        if (!$action) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta - action is required');
        }
        if (!$entity) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta requires an entity passed as an argument');
        }
        if (!$memberId) {
            throw new \InvalidArgumentException('ActionLogger::addForEntityDelta - memberId is required');
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
     * @param array $delta
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
        if ($this->session) {
            $ipAddress = $this->session->get('ipAddress');
            $userAgent = $this->session->get('user-agent');
        }

        $addedOnWeek = Util::getDateWeek(time());

        $entityId = null;

        if ($entity) {
            $entityIds = $this
                ->entityMetaHelper
                ->getLogicalIdentifierFieldNames($entity);

            if (count($entityIds) > 1) {
                throw new RuntimeException('Multi-id entities not supported');
            }

            $entityId = $entity->{"get{$entityIds[0]}"}();
        }

        $doc = [];

        if ($entityId) {
            $doc['_id'] = $entityId;
        } else {
            $doc['_id'] = $memberId;
        }

        $doc['actionCode']  = $action;
        $doc['memberId']    = $memberId;
        $doc['source']      = $this->source;
        $doc['ipAddress']   = $ipAddress;
        $doc['userAgent']   = $userAgent;
        $doc['comment']     = $comment;
        $doc['addedOn']     = time();
        $doc['addedOnWeek'] = $addedOnWeek;

        if ($entity) {
            $doc['affectedEntity']['class'] =
                $this->entityMetaHelper->getShortClassName($entity);
            $doc['affectedEntity']['id'] = $entityId;
            $doc['affectedEntity']['properties'] = $delta;
        }

        return json_encode($doc);
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

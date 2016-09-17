<?php

namespace CTLib\Component\ActionLog;

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

    const AUDIT_LOG_API_PATH = '/actionLogs';

    /**
     * @var EntityManager
     */
     protected $entityManager;

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
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     * @param Session $session
     */
    public function __construct(
        $entityManager,
        $ctApiCaller,
        $session
    ) {
        $this->entityManager    = $entityManager;
        $this->ctApiCaller      = $ctApiCaller;
        $this->session          = $session;
        $this->entityMetaHelper = $this->entityManager->getEntityMetaHelper();
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param int $action
     * @param BaseEntity $entity
     * @param int $memberId
     * @param string $source
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function add(
        $action,
        $entity     = null,
        $memberId   = null,
        $source     = null,
        $comment    = null
    ) {
        if (!$action) {
            throw new \Exception('ActionLogger::add - action is required');
        }

        $logData = '';
        $entityId = null;

        if ($entity) {
            $entityIds = $this
                ->entityMetaHelper
                ->getLogicalIdentifierFieldNames($entity);
            $logData = '"affectedEntityId":'
                .$entity->{"get{$entityIds[0]}"}();
            $entityId = $entity->{"get{$entityIds[0]}"}();
        }

        $logData = $this->compileActionLogDocument(
            $action,
            $entity,
            $entityId,
            $memberId,
            $source,
            $comment,
            $logData
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
     * @param string $source
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addWithTracking(
        $action,
        $entity,
        $delta,
        $memberId   = null,
        $source     = null,
        $comment    = null
    ) {
        // No changes, don't bother logging.
        if (!$delta) {
            return;
        }

        if (!$action) {
            throw new \Exception('ActionLogger::addWithTracking - action is required');
        }

        if (!$entity) {
            throw new \Exception('ActionLogger::addWithTracking requires and entity passed as an argument');
        }

        $entityIds = $this
            ->entityMetaHelper
            ->getLogicalIdentifierFieldNames($entity);

        $entityId = $entity->{"get{$entityIds[0]}"}();

        $logData = '"affectedEntityId":'.$entityId.',';
        $logData .= $delta;

        $logData = $this->compileActionLogDocument(
            $action,
            $entity,
            $entityId,
            $memberId,
            $source,
            $comment,
            $logData
        );

        $this->addLogEntry($logData);
    }

    /**
     * Helper method to construct a partial actionLog JSON
     * document.
     *
     * @param int $action
     * @param BaseEntity $entity
     * @param int $entityId
     * @param int $memberId
     * @param string $source
     * @param string $comment
     * @param string $logData
     *
     * @return string
     */
    protected function compileActionLogDocument(
        $action,
        $entity     = null,
        $entityId   = null,
        $memberId   = null,
        $source     = null,
        $comment    = null,
        $logData    = ''
    ) {
        if (!$memberId) {
            $memberId = $this->session->get('memberId');
        }

        if (!$memberId && $entity) {
            $memberId = method_exists($entity, 'getExecMemberId')
                ? $entity->getExecMemberId()
                : 0;
        }

        $ipAddress = $this->session->get('ipAddress');
        $userAgent = $this->session->get('user-agent');

        $addedOnWeek = $this->getDateWeek(time());

        $doc = '{';

        if ($entityId) {
            $doc .= '"_id":'.$entityId;
        } else {
            $doc .= '"_id":'.$memberId;
        }

        $doc .= ',"actionCode":'.$action.','
             . '"memberId":'.$memberId.','
             . '"source":"'.$source.'",'
             . '"ipAddress":"'.$ipAddress.'",'
             . '"userAgent":"'.$userAgent.'",'
             . '"comment":"'.$comment.'",'
             . '"addedOn":'.time().','
             . '"addedOnWeek":'.$addedOnWeek;

        if ($logData) {
            $doc .= ','.$logData;
        }
        $doc .= '}';

        return $doc;
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

    /**
     * Helper method to get the DateWeek of the given timestamp.
     *
     * @param integer timestamp
     *
     * @return integer
     */
    protected function getDateWeek($timestamp)
    {
        $timezone = new \DateTimeZone('UTC');
        $datetime = new \DateTime("now", $timezone);
        $datetime->setTimestamp($timestamp);
        $dateWeek = $datetime->format('W');
        return (int)$dateWeek;
    }
}

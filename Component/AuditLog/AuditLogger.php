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
     protected $EntityManager;

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
            throw new \Exception('AuditLogger::add - action is required');
        }

        $logData = $this->compileActionLogDocumentPart(
            $action,
            $entity,
            $memberId,
            $source,
            $comment
        );

        if ($entity) {
            $entityId = $this
                ->entityMetaHelper
                ->getLogicalIdentifierFieldNames($entity);
            $logData .= ',"affectedEntityId":'.$entityId[0];
        }

        $logData .= '}';

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

        if (!$entity) {
            throw new \Exception('AuditLogger::addWithTracking requires and entity passed as an argument');
        }

        $logData = $this->compileActionLogDocumentPart(
            $action,
            $entity,
            $memberId,
            $source,
            $comment
        );

        $entityId = $this
            ->entityMetaHelper
            ->getLogicalIdentifierFieldNames($entity);

        $logData =. ',"affectedEntityId":'.$entityId[0].',';
        $logData .= $delta
        $logData .= '}';

        $this->addLogEntry($logData);
    }

    /**
     * Helper method to construct a partial actionLog JSON
     * document.
     *
     * @param int $action
     * @param BaseEntity $entity
     * @param int $memberId
     * @param string $source
     * @param string $comment
     *
     * @return string
     */
    protected function compileActionLogDocumentPart(
        $action,
        $entity     = null,
        $memberId   = null,
        $source     = null,
        $comment    = null
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

        $logData = '{'
             . '"actionCode":'.$action.','
             . '"memberId":'.$memberId.','
             . '"source":"'.$source.'",'
             . '"ipAddress":"'.$ipAddress.'",'
             . '"userAgent":"'.$userAgent.'",'
             . '"comment":'.$comment.'",'
             . '"addedOnWeek":'.$addedOnWeek;

        return $logData;
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
        $response = $this->ctApiCaller->post(
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

<?php

namespace CTLib\Component\AuditLog;

use CTLib\Entity\TrackableEntity;
use CTLib\Entity\AuditLog;

/**
 * Class AuditLogger
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class AuditLogger
{
    const AUDIT_LOG_API_PATH = '/auditLog';

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
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     * @param Session $session
     */
    public function __construct(
        $entityManager,
        $ctApiCaller,
        $session
    ) {
        $this->entityManager = $entityManager;
        $this->ctApiCaller   = $ctApiCaller;
        $this->session       = $session;
    }

    /**
     * @param TrackableEntity $entity
     */
    public function startNew(TrackableEntity $entity)
    {
        $entity->beginNew();
    }

    /**
     * @param TrackableEntity $entity
     * @param int             $action
     * @param string          $source
     * @param int             $memberId
     *
     * @return AuditLog
     *
     * @throws \Exception
     */
    public function endNew(
        TrackableEntity $entity,
        $action   = null,
        $memberId = null,
        $source   = 'OTP'
    ) {
        if (!$entity->getIsTracking()) {
            throw new \Exception("Entity is not being tracked");
        }

        $className = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->{"get{$className}Id"}();

        $logData = $this->compileAuditData($entity, $action, $memberId, $source);

        $auditEntry =
            $this->addLogEntry($entityId, $memberId, $logData, $action, $source);

        $entity->endNew();

        return $auditEntry;
    }

    /**
     * @param TrackableEntity $entity
     */
    public function startEdit(TrackableEntity $entity)
    {
        $entity->beginEdit();
    }

    /**
     * @param TrackableEntity $entity
     * @param int             $action
     * @param string          $source
     * @param int             $memberId
     *
     * @return AuditLog
     *
     * @throws \Exception
     */
    public function endEdit(
        TrackableEntity $entity,
        $action   = null,
        $memberId = null,
        $source   = 'OTP'
    ) {
        if (!$entity->getIsTracking()) {
            throw new \Exception("Entity is not being tracked");
        }

        $className = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->{"get{$className}Id"}();

        $logData = $this->compileAuditData($entity, $action, $memberId, $source);

        $auditEntry =
            $this->addLogEntry($entityId, $memberId, $logData, $action, $source);

        $entity->endEdit();

        return $auditEntry;
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param int $action
     * @param TrackableEntity $entity
     * @param string $data
     * @param int $memberId
     * @param string $source
     * @param string $comment
     *
     * @return void
     */
    public function add(
        $action,
        $entity     = null,
        $data       = null,
        $memberId   = null,
        $source     = null,
        $comment    = null
    ) {
        $className = 'NA';
        $entityId  = 0;
        $state     = 0;

        if (!$memberId) {
            $memberId = $this->session->get('memberId');
        }
        $ipAddress = $this->session->get('ipAddress');
        $userAgent = $this->session->get('user-agent');
        if (!$memberId && $entity) {
            $memberId = method_exists($entity, 'getExecMemberId')
                ? $entity->getExecMemberId()
                : 0;
        }

        if ($entity) {
            $className = (new \ReflectionClass($entity))->getShortName();
            $entityId = $entity->{"get{$className}Id"}();

            switch ($entity->getTrackingState()) {
                case BaseEntity::CREATE:
                    $state = "NEW";
                    break;
                case BaseEntity::MODIFY:
                    $state = "MODIFIED";
                    break;
                default:
                    $state = "UNCHANGED";
                    break;
            }
        }

        $addedOnWeek = $this->getDateWeek(time());

        $logData = '{"'.$className.'":{'
             . '"id":'.$entityId.','
             . '"state":"'.$state.'",'
             . '"memberId":'.$memberId.','
             . '"action":'.$action.','
             . '"source":"'.$source.'",'
             . '"ipAddress":"'.$ipAddress.'",'
             . '"userAgent":"'.$userAgent.'",'
             . '"data":"'.$data.'",'
             . '"comment":'.$comment.'",'
             . '"addedOnWeek":'.$addedOnWeek
             . '}}';

        $this->addLogEntry(
            $entityId,
            $memberId,
            $logData,
            $action,
            $source,
            $comment
        );
    }

    /**
     * Compile audit data with old and new values into JSON.
     *
     * @param TrackableEntity $entity
     * @param int $action
     * @param int $memberId
     * @param string $source
     *
     * @return string
     */
    protected function compileAuditData(
        $entity,
        $action,
        $memberId,
        $source
    ) {
        $properties = [];
        $modifiedProperties = $entity->getModifiedProperties();

        $methods = get_class_methods($entity);

        // Get all the entity's current property values.
        foreach ($methods as $method) {
            if (strpos($method, 'get') === 0) {
                $propName = lcfirst(substr($method, 3, strlen($method) - 3));
                $properties[$propName] = $entity->$method();
            }
        }

        switch ($entity->getTrackingState()) {
            case BaseEntity::CREATE:
                $state = "NEW";
                break;
            case BaseEntity::MODIFY:
                $state = "MODIFIED";
                break;
            default:
                $state = "UNCHANGED";
                break;
        }

        $className = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->{"get{$className}Id"}();

        $logData = '{"'.$className.'":{"id":'.$entityId.','
             . '"state":"'.$state.'","properties":[';

        // If the property is part of the modified properties, include
        // it in the log entry.
        foreach ($properties as $prop => $value) {
            if (array_key_exists($prop, $modifiedProperties)) {
                $logData .= '"'.$prop.'":{'
                    . '"oldValue":"'.$modifiedProperties[$prop].'",'
                    . '"newValue": "'.$value.'"'
                    . '},';
            }
        }

        $pos = strrpos($logData, ',');
        if ($pos !== false) {
            $logData = substr_replace($logData, '', $pos, 1);
        }

        $logData .= '],';
        $logData .= '"affectedEntityIds": [';

        $entityIds = array_values($this->entityManager->getEntityId($entity));

        foreach ($entityIds as $id) {
            $logData .= $id.',';
        }

        $pos = strrpos($logData, ',');
        if ($pos !== false) {
            $logData = substr_replace($logData, '', $pos, 1);
        }
        $logData .= '],';

        if (!$memberId) {
            $memberId = $this->session->get('memberId');
        }
        $ipAddress = $this->session->get('ipAddress');
        $userAgent = $this->session->get('user-agent');
        if (!$memberId) {
            $memberId = method_exists($entity, 'getExecMemberId')
                ? $entity->getExecMemberId()
                : 0;
        }

        $addedOnWeek = $this->getDateWeek(time());

        $logData .= '"memberId":'.$memberId.','
            . '"action":'.$action.','
            . '"source":"'.$source.'",'
            . '"ipAddress":"'.$ipAddress.'",'
            . '"userAgent":"'.$userAgent.'"',
            . '"addedOnWeek":'.$addedOnWeek.'"'
            . '}}';

        return $logData;
    }

    /**
     * Save the audit log entry to the database.
     *
     * @param int       $entityId
     * @param int       $memberId
     * @param string    $log
     * @param int       $action
     * @param string    $source
     * @param string    $comment
     *
     * @return AuditLog
     */
    protected function addLogEntry(
        $entityId,
        $memberId,
        $log,
        $action,
        $source,
        $comment = null
    ) {
        $auditLog = new AuditLog([
            'affectedEntityId' => $entityId,
            'memberId'         => $memberId,
            'action'           => $action,
            'auditData'        => $log,
            'source'           => $source,
            'comment'          => $comment
        ]);

        $this->entityManager->insert($auditLog);

        /*
        $response = $this->ctApiCaller->post(
            self::AUDIT_LOG_API_PATH,
            $log
        );
        */

        return $auditLog;
    }

    /**
     * Helper method to get the DateWeek of the given timestamp.
     *
     * @param integer timestamp
     *
     * @return integer
     */
    private function getDateWeek($timestamp)
    {
        $timezone = new \DateTimeZone('UTC');
        $datetime = new \DateTime("now", $timezone);
        $datetime->setTimestamp($timestamp);
        $dateWeek = $datetime->format('W');
        return (int)$dateWeek;
    }
}

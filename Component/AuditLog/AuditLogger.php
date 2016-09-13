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
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var MemberHelper
     */
    protected $memberHelper;

    /**
     * @var Session
     */
    protected $session;


    /**
     * @param EntityManager $entityManager
     * @param MemberHelper $memberHelper
     * @param Session $session
     */
    public function __construct($entityManager, $memberHelper, $session)
    {
        $this->entityManager = $entityManager;
        $this->memberHelper  = $memberHelper;
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

        $log = $this->compileAuditData($entity, $action, $memberId, $source);

        $auditEntry = $this->addLogEntry($entityId, 0, $log, $action, $source);

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

        $log = $this->compileAuditData($entity, $action, $memberId, $source);

        $auditEntry = $this->addLogEntry($entityId, $memberId, $log, $action, $source);

        $entity->endEdit();

        return $auditEntry;
    }

    /**
     * @param $action
     * @param $affectedEntity
     * @param $memberId
     * @param $comment
     *
     */
    public function add(
        $action,
        $affectedEntity = null,
        $data           = null,
        $memberId       = null,
        $comment        = null
    ) {

        $auditLog = new AuditLog([
            'affectedEntityId' => $entityId,
            'memberId'         => $memberId,
            'action'           => $action,
            'auditData'        => $data,
            'comment'          => $comment
        ]);

        $this->entityManager->insert($auditLog);
    }

    /**
     * @param $entity
     * @param $action
     * @param $memberId
     * @param $source
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

        $log = '{"'.$className.'":{"id":'.$entityId.','
             . '"state":"'.$state.'","properties":[';

        // If the property is part of the modified properties, include
        // it in the log entry.
        foreach ($properties as $prop => $value) {
            if (array_key_exists($prop, $modifiedProperties)) {
                $log .= '"'.$prop.'":{'
                    . '"oldValue":"'.$modifiedProperties[$prop].'",'
                    . '"newValue": "'.$value.'"'
                    . '},';
            }
        }

        $pos = strrpos($log, ',');
        $log = substr_replace($log, '', $pos, 1);

        $log .= '],';
        $log .= '"affectedEntityIds": [';

        $entityIds = array_values($this->entityManager->getEntityId($entity));

        foreach ($entityIds as $id) {
            $log .= $id.',';
        }

        $pos = strrpos($log, ',');
        $log = substr_replace($log, '', $pos, 1);
        $log .= '],';

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

        $log .= '"memberId":'.$memberId.','
            .  '"action":'.$action.','
            .  '"source":"'.$source.'",'
            .  '"ipAddress":"'.$ipAddress.'",'
            .  '"userAgent":"'.$userAgent.'"'
            .  '}}';

        return $log;
    }

    /**
     * @param int       $entityId
     * @param int       $memberId
     * @param string    $log
     * @param int       $action
     * @param string    $source
     *
     * @return AuditLog
     */
    protected function addLogEntry(
        $entityId,
        $memberId,
        $log,
        $action,
        $source
    ) {
        $auditLog = new AuditLog([
            'affectedEntityId' => $entityId,
            'memberId'         => $memberId,
            'action'           => $action,
            'auditData'        => $log,
            'source'           => $source
        ]);

        $this->entityManager->insert($auditLog);

        return $auditLog;
    }
}

<?php

namespace CTLib\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AuditLog
 *
 * @ORM\Table(name="audit_log")
 * @ORM\Entity(repositoryClass="CTLib\Repository\BaseRepository")
 * @ORM\HasLifecycleCallbacks
 */
class AuditLog extends BaseEntity
{
    /**
     * @var integer $auditLogId
     *
     * @ORM\Column(name="audit_log_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $auditLogId;

    /**
     * @var string $affectedEntityId
     *
     * @ORM\Column(name="affected_entity_id", type="string", nullable=true, length=10)
     */
    private $affectedEntityId;

    /**
     * @var integer $memberId
     *
     * @ORM\Column(name="member_id", type="integer", nullable=true)
     */
    private $memberId;

    /**
     * @var integer $action
     *
     * @ORM\Column(name="action", type="integer", nullable=false)
     */
    private $action;

    /**
     * @var text $audit_data
     *
     * @ORM\Column(name="audit_data", type="text", nullable=true)
     */
    private $auditData;

    /**
     * @var text $comment
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    private $comment;

    /**
     * @var string $source
     *
     * @ORM\Column(name="source", type="string", nullable=true, length=10)
     */
    private $source;

    /**
     * @var integer $addedOn
     *
     * @ORM\Column(name="added_on", type="integer", nullable=false)
     */
    protected $addedOn;


    /**
     * @var array
     */
    private $unserializedAuditData;


    /**
     * Set auditLogId
     *
     * @param integer $auditLogId
     */
    public function setAuditLogId($auditLogId)
    {
        $this->auditLogId = $auditLogId;
    }

    /**
     * Get auditLogId
     *
     * @return integer $auditLogId
     */
    public function getAuditLogId()
    {
        return $this->auditLogId;
    }

    /**
     * Set affectedEntityId
     *
     * @param string $affectedEntityId
     */
    public function setAffectedEntityId($affectedEntityId)
    {
        $this->affectedEntityId = $affectedEntityId;
    }

    /**
     * Get affectedEntityId
     *
     * @return string $affectedEntityId
     */
    public function getAffectedEntityId()
    {
        return $this->affectedEntityId;
    }

    /**
     * Set memberId
     *
     * @param integer $memberId
     */
    public function setMemberId($memberId)
    {
        $this->memberId = $memberId;
    }

    /**
     * Get memberId
     *
     * @return integer $memberId
     */
    public function getMemberId()
    {
        return $this->memberId;
    }

    /**
     * Set action
     *
     * @param integer $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * Get action
     *
     * @return integer $action
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set auditData
     *
     * @param array $auditData
     */
    public function setAuditData($auditData)
    {
        $this->auditData = json_encode($auditData);
        $this->unserializedAuditData = $auditData;
    }

    /**
     * Get auditData
     *
     * @return array $auditData
     */
    public function getAuditData()
    {
        return $this->unserializedAuditData;
    }

    /**
     * Set comment
     *
     * @param text $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * Get comment
     *
     * @return text $comment
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set source
     *
     * @param string $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * Get source
     *
     * @return string $source
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @ORM\PostLoad
     */
    public function postLoad()
    {
        if ($this->auditData) {
            $this->unserializedAuditData = json_decode($this->auditData, true);
        } else {
            $this->unserializedAuditData = [];
        }
    }
}

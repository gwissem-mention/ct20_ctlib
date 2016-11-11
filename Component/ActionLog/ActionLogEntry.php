<?php
namespace CTLib\Component\ActionLog;

use CTLib\Util\Util;
use CTLib\Component\Doctrine\ORM\EntityDelta;

/**
 * Represents individual action log entry.
 *
 * @author Mike Turoff
 */
class ActionLogEntry implements \JsonSerializable
{

    /**
     * "User ID" used to record system actions.
     */
    const SYSTEM_USER_ID = "__SYS__";

    /**
     * Code representing logged action.
     * @var integer
     */
    protected $actionCode;

    /**
     * Source of log entry.
     * @var string
     */
    protected $source;

    /**
     * Class of affected entity.
     * @var string
     */
    protected $affectedEntityClass;

    /**
     * ID field=>value of affected entity.
     * @var array
     */
    protected $affectedEntityId;

    /**
     * Field value delta of affected entity.
     * @var EntityDelta
     */
    protected $affectedEntityDelta;

    /**
     * Class of parent entity.
     * @var string
     */
    protected $parentEntityClass;

    /**
     * ID value of parent entity.
     * @var mixed
     */
    protected $parentEntityId;

    /**
     * Set of filters assigned to parent entity.
     * @var array
     */
    protected $parentEntityFilters;

    /**
     * Timestamp when log added.
     * @var integer
     */
    protected $addedOn;

    /**
     * ISO week when log added (used as shard key)
     * @var integer
     */
    protected $addedOnWeek;

    /**
     * ID of user that executed action.
     * @var mixed
     */
    protected $userId;

    /**
     * Role of user that executed action.
     * @var mixed
     */
    protected $userRole;

    /**
     * IP Address of user that executed action.
     * @var string
     */
    protected $userIpAddress;

    /**
     * Comment by user when executing action.
     * @var string
     */
    protected $comment;

    /**
     * Miscellaneous key/value pairs of additional log information.
     * @var array
     */
    protected $extra;


    /**
     * @param integer $actionCode
     * @param string $source
     * @param string $affectedEntityClass
     * @param array $affectedEntityId
     * @param string $parentEntityClass
     * @param mixed $parentEntityId
     * @param array $parentEntityFilters
     */
    public function __construct(
        $actionCode,
        $source,
        $affectedEntityClass,
        array $affectedEntityId,
        $parentEntityClass,
        $parentEntityId,
        array $parentEntityFilters
    ) {
        $this->actionCode           = $actionCode;
        $this->source               = $source;
        $this->affectedEntityClass  = $affectedEntityClass;
        $this->affectedEntityId     = $affectedEntityId;
        $this->affectedEntityDelta  = null;
        $this->parentEntityClass    = $parentEntityClass;
        $this->parentEntityId       = $parentEntityId;
        $this->parentEntityFilters  = $parentEntityFilters;
        $this->addedOn              = time();
        $this->addedOnWeek          = Util::getDateWeek($this->addedOn);
        $this->userId               = self::SYSTEM_USER_ID;
        $this->userRole             = null;
        $this->userIpAddress        = null;
        $this->comment              = null;
        $this->extra                = [];
    }

    /**
     * Sets userId.
     * @param mixed $userId
     * @return ActionLogEntry
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Sets userRole.
     * @param string $userRole
     * @return ActionLogEntry
     */
    public function setUserRole($userRole)
    {
        $this->userRole = $userRole;
        return $this;
    }

    /**
     * Sets userIpAddress.
     * @param string $userIpAddress
     * @return ActionLogEntry
     */
    public function setUserIpAddress($userIpAddress)
    {
        $this->userIpAddress = $userIpAddress;
        return $this;
    }

    /**
     * Sets comment.
     * @param string $comment
     * @return ActionLogEntry
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Adds extra value.
     * @param string $key
     * @param mixed $value
     * @return ActionLogEntry
     */
    public function addExtraValue($key, $value)
    {
        $this->extra[$key] = $value;
        return $this;
    }

    /**
     * Sets all extra values (overrides existing).
     * @param array $extra
     * @return ActionLogEntry
     */
    public function setExtraValues(array $extra)
    {
        $this->extra = $extra;
        return $this;
    }

    /**
     * Sets affectedEntityDelta.
     * @param EntityDelta
     * @return ActionLogEntry
     */
    public function setAffectedEntityDelta(EntityDelta $delta)
    {
        $this->affectedEntityDelta = $delta;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $user = [
            'id'        => $this->userId,
            'role'      => $this->userRole,
            'ipAddress' => $this->userIpAddress
        ];

        $affectedEntity = [
            'class'     => $this->affectedEntityClass,
            'id'        => $this->affectedEntityId,
            'delta'     => $this->affectedEntityDelta ?: []
        ];

        $parentEntity = [
            'class'     => $this->parentEntityClass,
            'id'        => $this->parentEntityId,
            'filters'   => $this->parentEntityFilters
        ];

        return [
            'actionCode'        => $this->actionCode,
            'source'            => $this->source,
            'user'              => $user,
            'comment'           => $this->comment,
            'addedOn'           => $this->addedOn,
            'addedOnWeek'       => $this->addedOnWeek,
            'extra'             => $this->extra,
            'affectedEntity'    => $affectedEntity,
            'parentEntity'      => $parentEntity
        ];
    }

}

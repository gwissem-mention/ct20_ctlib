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
    const SYSTEM_USER_ID = "__SYSTEM__";

    /**
     * Logged action.
     * @var string
     */
    protected $action;

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
     * Session ID of user that executed action.
     * @var string
     */
    protected $userSessionId;

    /**
     * User agent that executed action.
     * @var string
     */
    protected $userAgent;

    /**
     * Users full name that executed action.
     * @var string
     */
    protected $userFullName;

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
     * @param string $action
     * @param string $source
     * @param string $affectedEntityClass
     * @param array $affectedEntityId
     * @param string $parentEntityClass
     * @param mixed $parentEntityId
     * @param array $parentEntityFilters
     */
    public function __construct(
        $action,
        $source,
        $affectedEntityClass,
        array $affectedEntityId,
        $parentEntityClass,
        $parentEntityId,
        array $parentEntityFilters
    ) {
        $this->action               = $action;
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
        $this->userSessionId        = null;
        $this->userAgent            = null;
        $this->userFullName         = null;
        $this->comment              = null;
        $this->extra                = [];
    }

    /**
     * Sets all user properties based on passed $user.
     * @param ActionLogUserInterface $user
     * @return ActionLogEntry
     */
    public function setUser(ActionLogUserInterface $user)
    {
        $this->userId           = $user->getUserIdForActionLog();
        $this->userRole         = $user->getRoleForActionLog();
        $this->userIpAddress    = $user->getIpAddressForActionLog();
        $this->userSessionId    = $user->getSessionIdForActionLog();
        $this->userAgent        = $user->getAgentForActionLog();
        $this->userFullName     = $user->getNameForActionLog();
        return $this;
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
     * Sets userSessionId.
     * @param string $userSessionId
     * @return ActionLogEntry
     */
    public function setUserSessionId($userSessionId)
    {
        $this->userSessionId = $userSessionId;
        return $this;
    }

    /**
     * Sets userAgent.
     * @param string $userAgent
     * @return ActionLogEntry
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Sets userFullName.
     * @param string $userFullName
     * @return ActionLogEntry
     */
    public function setUserFullName($userFullName)
    {
        $this->userFullName = $userFullName;
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
            'ipAddress' => $this->userIpAddress,
            'sessionId' => $this->userSessionId,
            'agent'     => $this->userAgent,
            'name'      => $this->userFullName
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
            'action'            => $this->action,
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

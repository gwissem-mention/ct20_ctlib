<?php
namespace CTLib\Component\ActionLog;


use CTLib\Util\Util;
use CTLib\Component\Doctrine\ORM\EntityDelta;



class ActionLogEntry implements \JsonSerializable
{

    const SYSTEM_MEMBER_ID = 0;


    public function __construct(
        $actionCode,
        $source,
        $parentEntityClass,
        $parentEntityId,
        array $parentEntityFilters,
        $affectedEntityClass,
        array $affectedEntityId
    ) {
        $this->actionCode           = $actionCode;
        $this->source               = $source;
        $this->parentEntityClass    = $parentEntityClass;
        $this->parentEntityId       = $parentEntityId;
        $this->parentEntityFilters  = $parentEntityFilters;
        $this->affectedEntityClass  = $affectedEntityClass;
        $this->affectedEntityId     = $affectedEntityId;
        $this->affectedEntityDelta  = null;
        $this->addedOn              = time();
        $this->addedOnWeek          = Util::getDateWeek($this->addedOn);
        $this->memberId             = self::SYSTEM_MEMBER_ID;
        $this->comment              = null;
        $this->extra                = [];
    }

    public function setMemberId($memberId)
    {
        $this->memberId = $memberId;
        return $this;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    public function setExtraValues(array $extra)
    {
        $this->extra = $extra;
        return $this;
    }

    public function addExtraValue($key, $value)
    {
        $this->extra[$key] = $value;
        return $this;
    }

    public function setAffectedEntityDelta(EntityDelta $delta)
    {
        $this->affectedEntityDelta = $delta;
        return $this;
    }

    public function jsonSerialize()
    {
        $parentEntity = [
            'class'     => $this->parentEntityClass,
            'id'        => $this->parentEntityId,
            'filters'   => $this->parentEntityFilters
        ];

        $affectedEntity = [
            'class'     => $this->affectedEntityClass,
            'id'        => $this->affectedEntityId,
            'delta'     => $this->affectedEntityDelta ?: []
        ];

        return [
            'actionCode'        => $this->actionCode,
            'source'            => $this->source,
            'memberId'          => $this->memberId,
            'comment'           => $this->comment,
            'addedOn'           => $this->addedOn,
            'addedOnWeek'       => $this->addedOnWeek,
            'extra'             => $this->extra,
            'parentEntity'      => $parentEntity,
            'affectedEntity'    => $affectedEntity
        ];
    }




}

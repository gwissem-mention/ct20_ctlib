<?php
namespace CTLib\Component\Push;


/**
 * Defines a push message.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class PushMessage
{
    
    /**
     * @var string
     */
    protected $devicePlatform;

    /**
     * @var string
     */
    protected $devicePushId;

    /**
     * @var string
     */
    protected $applicationPackageId;

    /**
     * @var string
     */
    protected $groupKey;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var integer
     */
    protected $timeToLive;

    /**
     * @var boolean
     */
    protected $delayWhileIdle;


    /**
     * @param string $devicePlatform        Device platform (i.e, "ANDROID") used to
     *                                      match with push driver.
     * @param string $devicePushId          Device ID registered with push service.
     * @param string $applicationPackageId  Package/App/Bundle identifier for
     *                                      receiving application.
     */
    public function __construct($devicePlatform, $devicePushId,
        $applicationPackageId=null)
    {
        $this->devicePlatform       = $devicePlatform;
        $this->devicePushId         = $devicePushId;
        $this->applicationPackageId = $applicationPackageId;
        $this->parameters           = array();
    }

    /**
     * Sets parameter to be included in message.
     *
     * Parameter for existing key will override existing value.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    /**
     * Sets multiple parameters to be included in message.
     *
     * Parameters for existing keys will override existing values.
     * 
     * @param array $parameters     array({$key} => {$value}, ...)
     * @return $this
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }

    /**
     * Sets group key used by push delivery service to collapse repeat
     * notifications.
     *
     * @param string $groupKey
     * @return $this
     */
    public function setGroupKey($groupKey)
    {
        $this->groupKey = $groupKey;
        return $this;
    }

    /**
     * Sets amount of time (in seconds) for message to persist on push server
     * when it cannot be delivered to device.
     *
     * @param integer $timeToLive
     * @return $this
     */
    public function setTimeToLive($timeToLive)
    {
        if (! is_int($timeToLive)) {
            throw new \Exception('$timeToLive must be integer');
        }
        $this->timeToLive = $timeToLive;
        return $this;
    }

    /**
     * Sets whether delivery of push message should be delayed when device
     * is idle.
     *
     * @param boolean $delayWhileIdle
     * @return $this
     */
    public function setDelayWhileIdle($delayWhileIdle)
    {
        if (! is_bool($delayWhileIdle)) {
            throw new \Exception('$delayWhileIdle must be boolean');
        }
        $this->delayWhileIdle = $delayWhileIdle;
        return $this;
    }

    /**
     * Returns $devicePlatform.
     *
     * @return string
     */
    public function getDevicePlatform()
    {
        return $this->devicePlatform;
    }

    /**
     * Returns $devicePushId.
     *
     * @return string
     */
    public function getDevicePushId()
    {
        return $this->devicePushId;
    }

    /**
     * Returns $applicationPackageId
     *
     * @return string
     */
    public function getApplicationPackageId()
    {
        return $this->applicationPackageId;
    }

    /**
     * Returns $groupKey.
     *
     * @return string
     */
    public function getGroupKey()
    {
        return $this->groupKey;
    }

    /**
     * Returns $parameters.
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns $timeToLive.
     *
     * @return integer
     */
    public function getTimeToLive()
    {
        return $this->timeToLive;
    }

    /**
     * Returns $delayWhileIdle.
     *
     * @return boolean
     */
    public function getDelayWhileIdle()
    {
        return $this->delayWhileIdle;
    }

    /**
     * Returns JSON-encoded representation of instance.
     *
     * @return string
     */
    public function __toString()
    {
        $values = array();
        foreach ($this as $attribute => $value) {
            $values[$attribute] = $value;
        }
        return json_encode($values);
    }


}
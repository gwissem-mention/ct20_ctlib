<?php
namespace CTLib\WebService;

/**
 * Web service response message.
 *
 * After upgrading to PHP 5.4, this class needs to implement JsonSerializable.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class ResponseMessage
{
    /**
     * @var integer
     */
    protected $commonStatusCode;

    /**
     * @var stdClass
     */
    protected $commonBuffer;

    /**
     * @var array
     */
    protected $updates;

    /**
     * @var array
     */
    protected $responseParts;
    

    /**
     * @param integer $commonStatusCode     Response status code.
     */
    public function __construct($commonStatusCode)
    {
        if (! is_int($commonStatusCode) || $commonStatusCode < 0) {
            throw new \Exception('$commonStatusCode must be unsigned int');
        }
        $this->commonStatusCode = $commonStatusCode;
        $this->commonBuffer     = new \stdClass;
        $this->responseParts    = array();
        $this->updates          = array();
    }

    /**
     * Adds key/value pair to common response buffer.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return ResponseMessage  Returns $this.
     */
    public function addToCommonBuffer($key, $value)
    {
        if (isset($this->commonBuffer->$key)) {
            throw new \Exception("Buffer already contains '$key'");
        }
        $this->commonBuffer->$key = $value;
        return $this;
    }

    /**
     * Sets entire common response buffer.
     *
     * Will replace any existing buffer.
     *
     * @param array $buffer     array($key => $value, ...)
     * @return ResponseMessage  Returns $this.
     */
    public function setCommonBuffer(\stdClass $buffer)
    {
        $this->commonBuffer = $buffer;
        return $this;
    }

    /**
     * Adds individual response part.
     *
     * @param ResponsePart $responsePart
     * @return ResponseMessage  Returns $this.
     */
    public function addPart($responsePart)
    {
        $this->responseParts[] = $responsePart;
        return $this;
    }

    /**
     * Adds multiple response parts.
     *
     * @param array $responseParts
     * @return ResponseMessage Returns $this.
     */
    public function addParts($responseParts)
    {
        $this->responseParts += $responseParts;
        return $this;
    }

    /**
     * Adds individual device update.
     *
     * @param DeviceUpdate $deviceUpdate
     * @return ResponseMessage  Returns $this.
     */
    public function addUpdate($deviceUpdate)
    {
        $this->updates[] = $deviceUpdate;
        return $this;
    }

    /**
     * Adds multiple device updates.
     *
     * @param array $deviceUpdates
     * @return ResponseMessage  Returns $this.
     */
    public function addUpdates($deviceUpdates)
    {
        $this->updates += $deviceUpdates;
        return $this;
    }

    /**
     * Returns instance for JSON serialization.
     *
     * After upgrading to PHP 5.4, ResponseMessage will implement
     * JsonSerializable so we can remove ResponseMessage::toJson.
     *
     * @return stdClass
     */
    public function jsonSerialize()
    {
        $rsp = new \stdClass;
        $rsp->common = new \stdClass;
        $rsp->common->status_code = $this->commonStatusCode;
        $rsp->common->buffer = $this->commonBuffer;

        if ($this->updates) {
            $rsp->updates = $this->updates;
        }

        if ($this->responseParts) {
            $rsp->responses = $this->responseParts;
        }
        return $rsp;
    }

    /**
     * Converts this response message to JSON.
     *
     * @return string
     */
    public function toJson()
    {
        $rsp = new \stdClass;
        $rsp->common = new \stdClass;
        $rsp->common->status_code = $this->commonStatusCode;
        $rsp->common->buffer = $this->commonBuffer;

        if ($this->updates) {
            $rsp->updates = array_map(
                function ($u) { return $u->forJson(); },
                $this->updates
            );    
        }

        if ($this->responseParts) {
            $rsp->responses = array_map(
                function ($part) { return $part->forJson(); },
                $this->responseParts
            );
        }
        return json_encode($rsp);
    }

    /**
     * Shortcut to creating ResponseMessage for thrown WebServiceException.
     *
     * @param WebServiceException $exception
     * @return ResponseMessage
     */
    public static function createForWebServiceException($exception)
    {
        $msg = new self($exception->getCode());
        $msg->setCommonBuffer($exception->getCommonBuffer());
        $msg->addToCommonBuffer('exception', $exception->getMessage());
        return $msg;
    }

}
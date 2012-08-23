<?php
namespace CTLib\WebService;

/**
 * Individual response part of overall response message.
 *
 * Each respnse part corresponds to 1 and only 1 request part from the request
 * message.
 *
 * After upgrading to PHP 5.4, this class needs to implement JsonSerializable.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class ResponsePart
{
    /**
     * @var integer
     */
    protected $statusCode;

    /**
     * @var string
     */
    protected $requestType;

    /**
     * @var integer
     */
    protected $requestId;

    /**
     * @var stdClass
     */
    protected $buffer;
    
    /**
     * @param integer   $statusCode     Response status code.
     * @param string    $requestType    Type of RequestPart that corresponds
     *                                  to this ResponsePart.
     * @param integer   $requestId      Id of RequestPart that corresponds to
     *                                  this ResponsePart.
     */
    public function __construct($statusCode, $requestType, $requestId=null)
    {
        if (! is_int($statusCode) || $statusCode < 0) {
            throw new \Exception('$statusCode must be unsigned int');
        }
        if (! is_null($requestId) && (! is_int($requestId) || $requestId <= 0)) {
            throw new \Exception('$requestId must be int greater than 0');
        }
        $this->statusCode   = $statusCode;
        $this->requestType  = $requestType;
        $this->requestId    = $requestId;
        $this->buffer       = new \stdClass;
    }

    /**
     * Adds key/value pair to response buffer.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return ResponsePart     Returns $this.
     */
    public function add($key, $value)
    {
        if (isset($this->buffer->$key)) {
            throw new \Exception("Buffer already contains '$key'");
        }
        $this->buffer->$key = $value;
        return $this;
    }

    /**
     * Append collection of key/value pairs to response buffer.
     *
     * @param Iterator $collection
     * @return ResponsePart Returns $this.
     */
    public function append($collection)
    {
        foreach ($collection as $key => $value) {
            $this->addToBuffer($key, $value);
        }
        return $this;
    }

    /**
     * Alias for forJson.
     *
     * After upgrading to PHP 5.4, ResponsePart will implement JsonSerializable
     * and require this method instead of forJson.
     */
    public function jsonSerialize()
    {
        return $this->forJson();
    }

    /**
     * Converts this ResponsePart to an array for JSON encoding when compiled
     * with the overall response message.
     *
     * @return array
     */
    public function forJson()
    {
        $part = new \stdClass;
        $part->status_code = $this->statusCode;
        $part->type = $this->requestType;

        if ($this->requestId) {
            $part->request_id = $this->requestId;
        }

        if (count((array) $this->buffer)) {
            $part->buffer = $this->buffer;    
        }
        return $part;
    }

    /**
     * Shortcut to creating ResponsePart based on corresponding RequestPart.
     *
     * @param integer   $statusCode
     * @param RequestPart   $requestPart
     *
     * @return ResponsePart
     */
    public static function createForRequest($statusCode, $requestPart)
    {
        return new self(
            $statusCode,
            $requestPart->getType(),
            $requestPart->getId()
        );
    }

    /**
     * Shortcut to creating ResponsePart based on corresponding RequestPart and
     * thrown WebServiceException.
     *
     * @param WebServiceException $exception
     * @param RequestPart $requestPart
     *
     * @return ResponsePart
     */
    public static function createForWebServiceException($exception, $requestPart)
    {
        $rspPart = self::createForRequest($exception->getCode(), $requestPart);
        $rspPart->add('exception', $exception->getMessage());
        return $rspPart;
    }

}
<?php
namespace CTLib\Component\Push;


/**
 * Captures push message delivery execeptions.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class PushDeliveryException extends \Exception
{
    
    /**
     * Unified error codes.
     */
    const REQUEST_INVALID                   = 1001;
    const REQUEST_TOO_BIG                   = 1002;

    const RESPONSE_INVALID                  = 2001;

    const SENDER_NOT_AUTHORIZED             = 3001;

    const SERVICE_INTERNAL_ERROR            = 4001;
    const SERVICE_TEMPORARILY_UNAVAILABLE   = 4002;
    const SERVICE_UNREACHABLE               = 4003;
    const SERVICE_UNKNOWN_ISSUE             = 4004;
    
    const DEVICE_INVALID_PUSH_ID            = 5001;
    const DEVICE_INVALID_SENDER             = 5002;
    const DEVICE_UNREGISTERED               = 5003;
    const DEVICE_UNKNOWN_ISSUE              = 5004;


    /**
     * @param integer $errorCode    Should be error code specified above.
     * @param string $message
     */
    public function __construct($errorCode, $message='')
    {
        $name = $this->getErrorString($errorCode) . " ({$errorCode})";
        $message = $message ? "{$name}\n\n{$message}" : $name;
        parent::__construct($message, $errorCode);
    }

    /**
     * Converts integer $errorCode into formal error string.
     *
     * @param integer $errorCode
     * @return string
     */
    protected function getErrorString($errorCode)
    {
        switch ($errorCode) {
            case self::REQUEST_INVALID:
                return 'Invalid Request';               
            case self::REQUEST_TOO_BIG:
                return 'Request Too Big';
            case self::RESPONSE_INVALID:
                return 'Invalid Response';
            case self::SENDER_NOT_AUTHORIZED:
                return 'Sender Not Authorized for Service';
            case self::SERVICE_INTERNAL_ERROR:
                return 'Internal Error Occurred with Service';
            case self::SERVICE_TEMPORARILY_UNAVAILABLE:
                return 'Service Temporariy Unavailable';
            case self::SERVICE_UNREACHABLE:
                return 'Service is Unreachable';
            case self::SERVICE_UNKNOWN_ISSUE:
                return 'Unknown Issue with Service';
            case self::DEVICE_INVALID_PUSH_ID:
                return 'Invalid Device Push ID';  
            case self::DEVICE_INVALID_SENDER:
                return 'Sender Not Authorized for Device';
            case self::DEVICE_UNREGISTERED:
                return 'Device Push ID is Not Registered';
            case self::DEVICE_UNKNOWN_ISSUE:
                return 'Unknown Issue Sending to Device';
            default:
                return '';
        }
    }


}
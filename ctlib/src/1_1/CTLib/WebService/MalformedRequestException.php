<?php
namespace CTLib\WebService;

/**
 * Exception thrown anytime part of the request message is malformed.
 * (i.e., missing a property, property has invalid value)
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class MalformedRequestException extends WebServiceException
{
    
    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct(StatusCode::MALFORMED_REQUEST, $message);
    }


}
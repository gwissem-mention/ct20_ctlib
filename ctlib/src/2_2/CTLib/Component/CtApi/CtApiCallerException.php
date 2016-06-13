<?php
namespace CTLib\Component\CtApi;

use CTLib\Util\Curl;

/**
 * Captures Api caller execeptions.
 *
 * @author Li Gao <lgao@celltrak.com>
 */
class CtApiCallerException extends \Exception
{
    
    /**
     * @param integer $errorCode  // http response error code.
     * @param Response $response
     * @param Curl $request
     */
    public function __construct($errorCode, $response, Curl $request)
    {
        $message = "Request: " . print_r($request, true) . "\nResponse: {$response}";
        parent::__construct($message, $errorCode);
    }

}
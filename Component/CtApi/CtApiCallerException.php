<?php
namespace CTLib\Component\CtApi;


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
     * @param string $request
     */
    public function __construct(
        $errorCode, 
        $response, 
        $request
    ) {
        $message = (string) $response . $request; 
        parent::__construct($message, $errorCode);
    }

}
<?php
namespace CTLib\WebService;

use CTLib\Util\Arr;

/**
 * Root exception for web service logic.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class WebServiceException extends \Exception
{

    protected $commonBuffer;


    /**
     * @param integer $code         Response status code.
     * @param string $message       Internal error message.
     * @param array $commonBuffer   Used to set common buffer of ResponseMessage.
     */
    public function __construct($code, $message='', $commonBuffer=null)
    {
        parent::__construct(
            $message ?: Arr::get($code, StatusCode::$messages, ''),
            $code
        );
        $this->commonBuffer = $commonBuffer ?: new \stdClass;
    }

    /**
     * Returns commmon buffer.
     *
     * @return array
     */
    public function getCommonBuffer()
    {
        return $this->commonBuffer;
    }

    public function __toString()
    {
        return "WebServiceException ({$this->getCode()}): " . $this->getMessage();
    }

}

<?php
namespace CTLib\Component\ObjectWalker;

class MalformedObjectException extends \Exception
{
    
    protected $object;

    public function __construct($message, $object)
    {
        parent::__construct($message);
        $this->object = $object;
    }

}
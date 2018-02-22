<?php
namespace CTLib\Component\MSTeams\Exception;

class NotAChildException extends \Exception
{
    public function __construct($child, $class)
    {
        parent::__construct();
        $this->message = "$child is not a valid child of $class \n" .
            "$this->file ($this->line)";
    }
}

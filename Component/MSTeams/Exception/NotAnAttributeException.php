<?php
namespace CTLib\Component\MSTeams\Exception;

class NotAnAttributeException extends \Exception
{
    public function __construct($attribute, $class)
    {
        parent::__construct();
        $this->message = "${attribute} is not a valid attribute of $class \n" .
            "$this->file ($this->line)";
    }
}


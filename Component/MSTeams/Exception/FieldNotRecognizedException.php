<?php
namespace CTLib\Component\MSTeams\Exception;

class FieldNotRecognizedException extends \Exception
{
    public function __construct($field, $class)
    {
        parent::__construct();
        $this->message = "field ${field} not recognized on {$class} \n".
            "$this->file ($this->line)";
    }
}


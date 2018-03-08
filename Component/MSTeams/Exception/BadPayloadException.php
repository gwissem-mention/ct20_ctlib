<?php
namespace CTLib\Component\MSTeams\Exception;

class BadPayloadException extends \Exception
{
    public function __construct($requestBody)
    {
        parent::__construct(
            'MSTeams complained of a bad payload. ' .
            'This usually means that a child object in the json was ' .
            "not an array. Body: \n$requestBody"
        );
    }
}


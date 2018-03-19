<?php
namespace CTLib\Component\MSTeams\Exception;

class DeliveryException extends \Exception
{
    public function __construct($response, $requestBody, $code)
    {
        parent::__construct(
            "Deliver Exception: \"$response\" was received while trying to " .
            "create Message Card with this body: \n$requestBody",
            $code
        );
    }
}


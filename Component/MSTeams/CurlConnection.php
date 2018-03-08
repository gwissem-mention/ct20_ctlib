<?php
namespace CTLib\Component\MSTeams;

use CTLib\Component\MSTeams\Exception\DeliveryException;
use CTLib\Component\MSTeams\Exception\BadPayloadException;

class CurlConnection
{
    /** @var curl $url */
    private $curl;

    /** @var string */
    private $channelId;

    /** @var string */
    private $authToken;

    /** @var string connector */
    private $connector;

    /** @var array Exception */
    private $errors;

    /** @var MessageCard */
    private $body;

    /**
     * @param string $channelId
     * @param string $authToken
     * @param string $connector
     */
    public function __construct($channelId, $authToken, $connector)
    {
        $this->channelId        = $channelId;
        $this->authToken        = $authToken;
        $this->connector        = $connector;

        $this->exceptions = [];
    }

    /**
     * @param MessageCard
     * @return bool of success
     */
    public function send(MessageCard $messageCard)
    {
        $this->curl = curl_init($this->getUrl());
        $this->body = json_encode($messageCard);
        $this->configureCurl();

        return !$this->foundErrors(curl_exec($this->curl));
    }

    /**
     * @return string
     */
    public function getChannelId()
    {
        return $this->channelId;
    }

    /**
     * @return string
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }

    /**
     * @return string
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * Compiles URL to post payload to
     * @return string
     */
    public function getUrl()
    {
        return "https://outlook.office.com/webhook/{$this->getChannelId()}/" .
            "{$this->getConnector()}/{$this->getAuthToken()}";
    }

    /**
     * @return array
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * Uses the current attributes to set the next curl correctly
     */
    private function configureCurl()
    {
        curl_setopt(
            $this->curl,
            CURLOPT_HTTPHEADER,
            ['Content-type: application/json']
        );
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->body);

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POST, true);
    }

    /**
     * @param string
     * @return array of exception
     */
    private function foundErrors($response)
    {
        // Currently the MSTeams API is really basic and return 200 for success
        // and 400 for errors
        $code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if ($response !== "1") {
            if (preg_match('/Bad payload/', $response)) {
                $this->exceptions[] = new BadPayloadException($this->body);
            } else {
                $this->exceptions[] = new DeliveryException($response, $this->body, $code);
            }
        }

        return (bool) $this->exceptions;
    }
}


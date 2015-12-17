<?php
namespace CTLib\Component\Push\Driver;

use CTLib\Component\Push\PushDriver,
    CTLib\Component\Push\PushMessage,
    CTLib\Component\Push\PushDeliveryException,
    CTLib\Util\Curl,
    CTLib\Util\Arr;


/**
 * Push driver for sending messages to Android devices.
 *
 * For more information on using Android push service, go to:
 * http://developer.android.com/guide/google/gcm/gcm.html
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class AndroidPushDriver implements PushDriver
{
    
    
    /**
     * @var string
     */
    protected $serviceUrl;

    /**
     * @var string
     */
    protected $serviceAuth;

    /**
     * @var Logger
     */
    protected $logger;


    /**
     * @param string $serviceUrl
     * @param string $serviceAuth
     * @param Logger $logger
     */
    public function __construct($serviceUrl, $serviceAuth, $logger)
    {
        $this->serviceUrl   = $serviceUrl;
        $this->serviceAuth  = $serviceAuth;
        $this->logger       = $logger;
    }

    /**
     * @inherit
     */
    public function send(PushMessage $message)
    {
        $this->logger->debug("Android push driver: send message {$message}");

        $requestBody = $this->buildRequestBody($message);

        $this->logger->debug("Android push driver: request body\n\n{$requestBody}");

        $request = new Curl($this->serviceUrl);
        $request->post = true;
        $request->httpheader = array(
            'Authorization: key=' . $this->serviceAuth,
            'Content-Type: application/json'
        );
        $request->postfields = $requestBody;

        $response = $request->exec();

        switch ($request->info(CURLINFO_HTTP_CODE)) {
            case 200:
                // Successfully communicated with Android push service.
                return $this->processResponse($response, $message);
            case 400:
                throw new PushDeliveryException(
                    PushDeliveryException::REQUEST_INVALID,
                    "BODY: {$request->postfields}"
                );
            case 401:
                throw new PushDeliveryException(
                    PushDeliveryException::SENDER_NOT_AUTHORIZED
                );
            case 404:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_UNREACHABLE,
                    "URL: {$this->serviceUrl}"
                );
            case 500:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_INTERNAL_ERROR
                );
            case 503:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_TEMPORARILY_UNAVAILABLE
                );
            default:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_UNKNOWN_ISSUE
                );
        }
    }

    /**
     * Builds HTTP request body posted to Android push service.
     *
     * @param Push\PushMessage $message
     * @return string   Returns JSON string.
     */
    protected function buildRequestBody($message)
    {
        // Android message is JSON format following this spec:
        //  {
        //      "registration_ids": ["pushId1", "pushId2", ...],
        //      "collapse_key": "optional_token_to_group_similar_messages",
        //      "data": { optional key/value pairs to pass in intent }
        //      "delay_while_idle": true/false on whether to delay delivery if
        //                          device is idle (defaults to false),
        //      "time_to_live": seconds for message to persist on Google server
        //                      when not able to deliver (defaults)
        //  }
        
        // Build body as array so we can convert to JSON.
        $body = array('registration_ids' => (array) $message->getDevicePushId());

        if ($data = $message->getParameters()) {
            $body['data'] = $data;
        }
        
        if (! is_null($message->getGroupKey())) {
            $body['collapse_key'] = $message->getGroupKey();
        }

        if (! is_null($message->getTimeToLive())) {
            $body['time_to_live'] = $message->getTimeToLive();
        }

        if (! is_null($message->getDelayWhileIdle())) {
            $body['delay_while_idle'] = $message->getDelayWhileIdle();
        }
        return json_encode($body);
    }

    /**
     * Processes response received from Android push service.
     *
     * @param string $response
     * @param PushMessage $message
     *
     * @return void
     * @throws PushDeliveryException    If error occurred sending message.
     */
    protected function processResponse($response, $message)
    {
        $decodedResponse = json_decode($response);
        
        if (is_null($decodedResponse)
            || ! isset($decodedResponse->results)
            || ! is_array($decodedResponse->results)
            || count($decodedResponse->results) != 1) {
            throw new PushDeliveryException(
                PushDeliveryException::RESPONSE_INVALID,
                $response
            );
        }

        $result = current($decodedResponse->results);
        
        if (isset($result->message_id)) {
            // Message sent successfully.
            return;
        }

        switch ($result->error) {
            case 'InvalidRegistration':
                throw new PushDeliveryException(
                    PushDeliveryException::DEVICE_INVALID_PUSH_ID,
                    "PUSH ID: {$message->getDevicePushId()}"
                );
            case 'MismatchSenderId':
                throw new PushDeliveryException(
                    PushDeliveryException::DEVICE_INVALID_SENDER,
                    "PUSH ID: {$message->getDevicePushId()}"
                );
            case 'NotRegistered':
                throw new PushDeliveryException(
                    PushDeliveryException::DEVICE_UNREGISTERED,
                    "PUSH ID: {$message->getDevicePushId()}"
                );
            case 'MessageTooBig':
                throw new PushDeliveryException(
                    PushDeliveryException::REQUEST_TOO_BIG,
                    (string) $message
                );
            case 'Unavailable':
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_TEMPORARILY_UNAVAILABLE
                );
            default:
                throw new PushDeliveryException(
                    PushDeliveryException::DEVICE_UNKNOWN_ISSUE
                );
        }
    }

}
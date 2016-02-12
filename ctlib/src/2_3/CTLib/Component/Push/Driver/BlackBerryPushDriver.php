<?php
namespace CTLib\Component\Push\Driver;

use CTLib\Component\Push\PushDriver,
    CTLib\Component\Push\PushMessage,
    CTLib\Component\Push\PushDeliveryException,
    CTLib\Util\Curl,
    CTLib\Util\Arr;


/**
 * Push driver for sending messages to BlackBerry devices.
 *
 * RIM doesn't provide a lot of great help for their push service. You can view
 * their info here:
 * https://developer.blackberry.com/devzone/develop/platform_services/push_resources.html
 *
 * Most of this code was modeled after CellTrak Canada's .NET implementation
 * and a sample PHP script posted by someone in a forum.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class BlackBerryPushDriver implements PushDriver
{
    
    // Message content boundary (just a random string of characters not ever
    // to be found in the actual message content).
    const CONTENT_BOUNDARY  = 'D8u3DPythIg#VR6+MLG5';


    /**
     * @var string
     */
    protected $serviceUrl;

    /**
     * @var string
     */
    protected $serviceAuth;

    /**
     * @var string
     */
    protected $appId;

    /**
     * @var integer
     */
    protected $ttlSeconds;

    /**
     * @var Logger
     */
    protected $logger;


    /**
     * @param string $serviceUrl
     * @param string $serviceAuth
     * @param string $appId
     * @param integer $ttlSeconds
     * @param Logger $logger
     */
    public function __construct(
                      $serviceUrl,
                      $serviceAuth,
                      $appId,
                      $ttlSeconds,
                      $logger)
    {
        $this->serviceUrl   = $serviceUrl;
        $this->serviceAuth  = $serviceAuth;
        $this->appId        = $appId;
        $this->ttlSeconds   = $ttlSeconds;
        $this->logger       = $logger;
    }

    /**
     * @inherit
     */
    public function send(PushMessage $message)
    {
        $this->logger->debug("BlackBerry push driver: send message {$message}");

        $pushMessageId  = uniqid($message->getDevicePushId(), true);
        $contentType    = 'Content-Type: multipart/related; ' .
                          'type="application/xml"; ' .
                          'boundary=' . self::CONTENT_BOUNDARY;
        $requestBody    = $this->buildRequestBody($message, $pushMessageId);

        $this->logger->debug("BlackBerry push driver: request body\n\n{$requestBody}");

        $request = new Curl($this->serviceUrl);
        $request->post              = true;
        $request->httpauth          = CURLAUTH_BASIC;
        $request->userpwd           = "{$this->appId}:{$this->serviceAuth}";
        $request->returntransfer    = true;
        $request->httpheader        = array($contentType);
        $request->postfields        = $requestBody;

        $response = $request->exec();
        $httpCode = $request->info(CURLINFO_HTTP_CODE);

        switch ($httpCode) {
            case 200:
                // Successfully communicated with BlackBerry push service.
                return $this->processResponse($response, $request, $message);
            case 404:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_UNREACHABLE
                );
            case 500:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_INTERNAL_ERROR
                );
            default:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_UNKNOWN_ISSUE,
                    "Service returned HTTP Code {$httpCode}"
                );
        }
    }

    /**
     * Builds request body following BlackBerry push spec.
     *
     * @param PushMessage $message
     * @param string $pushMessageId
     *
     * @return string
     */
    protected function buildRequestBody($message, $pushMessageId)
    {
        $deliveryMethod = 'unconfirmed';
        $deliverBefore  = strtotime("+{$this->ttlSeconds} seconds");
        $deliverBefore  = gmdate('Y-m-d\TH:i:s\Z', $deliverBefore);
        $params         = json_encode(
                            $message->getParameters(),
                            JSON_FORCE_OBJECT);
        $body           = <<<TXT
        --%1\$s
        Content-Type: application/xml; charset=UTF-8
        <?xml version="1.0"?>
        <!DOCTYPE pap PUBLIC "-//WAPFORUM//DTD PAP 2.0//EN" "http://www.wapforum.org/DTD/pap_2.0.dtd" [<?wap-pap-ver supported-versions="2.0"?>]>
        <pap>
            <push-message push-id="{$pushMessageId}" source-reference="{$this->appId}" deliver-before-timestamp="{$deliverBefore}">
                <address address-value="{$message->getDevicePushId()}"/>
                <quality-of-service delivery-method="{$deliveryMethod}"/>
            </push-message>
        </pap>
        --%1\$s
        Content-Type: application/json
        Push-Message-ID: {$pushMessageId}

        {$params}
        --%1\$s--
TXT;
        return sprintf($body, self::CONTENT_BOUNDARY);
    }

    /**
     * Processes XML response returned from push service.
     *
     * @param string $response
     * @param Curl $request
     * @param PushMessage $message
     *
     * @return void
     * @throws PushDeliveryException    If something went wrong.
     */
    protected function processResponse($response, $request, $message)
    {
        try {
            $pap        = new \SimpleXMLElement($response);
            $result     = $pap->{'badmessage-response'} ?:
                          $pap->{'push-response'}->{'response-result'};
            $resultCode = (string) $result['code'];
            $resultDesc = (string) $result['desc'];
        } catch (\Exception $e) {
            throw new PushDeliveryException(
                PushDeliveryException::RESPONSE_INVALID,
                $response
            );
        }
        
        switch ($resultCode) {
            case '1000':
            case '1001':
                // Message sent successfully.
                return;

            case '2000':
            case '2001':
            case '2003':
                throw new PushDeliveryException(
                    PushDeliveryException::REQUEST_INVALID,
                    $request->postfields
                );

            case '2002':
                throw new PushDeliveryException(
                    PushDeliveryException::DEVICE_INVALID_PUSH_ID,
                    "BB PIN: {$message->getDevicePushId()}"
                );

            default:
                throw new PushDeliveryException(
                    PushDeliveryException::SERVICE_UNKNOWN_ISSUE,
                    $resultDesc
                );
        }
    }


}
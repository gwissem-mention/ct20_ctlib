<?php
namespace CTLib\Component\Push\Driver;

use CTLib\Component\Push\PushDriver,
    CTLib\Component\Push\PushMessage,
    CTLib\Component\Push\PushDeliveryException,
    CTLib\Util\Arr;


/**
 * Push driver for sending messages to iOS devices.
 *
 * For more information on using iOS push service, go to:
 * http://developer.apple.com/library/mac/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Introduction/Introduction.html#//apple_ref/doc/uid/TP40008194-CH1-SW1
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class IOSPushDriver implements PushDriver
{
    
    /**
     * @var array
     */
    protected $services;


    /**
     * @param Container $container
     */
    public function __construct($container)
    {
        $this->services = $container->getParameter('push.driver.ios');
    }

    /**
     * @inherit
     */
    public function send(PushMessage $message)
    {
        $binaryMessage = $this->buildBinaryMessage($message);

        $service = $this->getService($message->getApplicationPackageId());

        $conn = $this
                ->openConnectionToPushServer(
                    $service['service_url'],
                    $service['cert_path'],
                    $service['cert_pass']);

        if (! $conn) {
            throw new PushDeliveryException(
                PushDeliveryException::SERVICE_UNREACHABLE,
                "URL: {$service['service_url']}"
            );
        }

        // Send it to the server
        if (fwrite($conn, $binaryMessage, strlen($binaryMessage))) {
            $this->closeConnectionToPushServer($conn);
            return;
        } else {
            $this->closeConnectionToPushServer($conn);
            throw new PushDeliveryException(
                PushDeliveryException::SERVICE_UNKNOWN_ISSUE,
                "MESSAGE: {$binaryMessage}"
            );
        }
    }

    /**
     * Returns server certificate that corresponds to application package
     * identifier.
     *
     * @param string $applicationPackageId
     *
     * @return array    array($certPath, $certPass)
     * @throws PushDeliveryException    If matching certificate not found.
     */
    protected function getService($applicationPackageId)
    {
        foreach ($this->services as $service) {
            $certPackageId  = Arr::mustGet('package_id', $service);
            
            if ($certPackageId === $applicationPackageId) {
                return $service;
            }
        }

        throw new PushDeliveryException(
            PushDeliveryException::DEVICE_INVALID_PACKAGE_ID,
            "PACKAGE ID: '{$applicationPackageId}'"
        );
    }

    /**
     * Opens connection to iOS push server.
     *
     * @param string $serviceUrl 
     * @param string $certPath  Path to certificate file.
     * @param string $certPass  Certificate file passphrase.
     *
     * @return resource
     */
    protected function openConnectionToPushServer($serviceUrl, $certPath,
        $certPass)
    {
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $certPath);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $certPass);

        // Open a socket connection to the iOS push server.
        return stream_socket_client(
            $serviceUrl,
            $errno,
            $errstr,
            60, // timeout
            STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT,
            $ctx
        );
    }

    /**
     * Closes connection to iOS push server.
     *
     * @param resource
     * @return void
     */
    protected function closeConnectionToPushServer($conn)
    {
        fclose($conn);
    }

    /**
     * Converts PushMessage into binary message following iOS spec.
     *
     * @param PushMessage $message
     * @return string
     */
    protected function buildBinaryMessage(PushMessage $message)
    {
        $payload = array('aps' => $message->getParameters());
        $payload = json_encode($payload);
        
        // Build the binary message.
        if ($message->getTimeToLive()) {
            // Use enhanced iOS notification.
            // TODO: Is this the right way to format?
            $binaryMessage =    chr(1) .                                
                                $message->getGroupKey() .
                                time() + $message->getTimeToLive();

        } else {
            // Use simple iOS notification.
            $binaryMessage = chr(0);
        }

        $binaryMessage .=   pack('n', 32) .
                            pack('H*', $message->getDevicePushId()) .
                            pack('n', strlen($payload)) .
                            $payload;
        return $binaryMessage;
    }


}
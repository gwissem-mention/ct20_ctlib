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
     * @var string
     */
    protected $serviceUrl;

    /**
     * @var string
     */
    protected $certPath;

    /**
     * @var string
     */
    protected $certPass;


    /**
     * @param Container $container
     */
    public function __construct($container)
    {
        $params = $container->getParameter('push.driver.ios');

        $this->serviceUrl   = Arr::mustGet('service_url', $params);
        $this->certPath     = Arr::mustGet('cert_path', $params);
        $this->certPass     = Arr::mustGet('cert_pass', $params);
    }

    /**
     * @inherit
     */
    public function send(PushMessage $message)
    {
        $conn = $this->openConnectionToPushServer();

        if (! $conn) {
            throw new PushDeliveryException(
                PushDeliveryException::SERVICE_UNREACHABLE,
                "URL: {$this->serviceUrl}"
            );
        }

        $binaryMessage = $this->buildBinaryMessage($message);

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
     * Opens connection to iOS push server.
     *
     * @return resource
     */
    protected function openConnectionToPushServer()
    {
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $this->certPath);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->certPass);

        // Open a socket connection to the iOS push server.
        return stream_socket_client(
            $this->serviceUrl,
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
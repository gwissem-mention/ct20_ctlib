<?php
namespace CTLib\Component\Push;


/**
 * Manages sending of push notifications to mobile devices.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class PushManager
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $drivers;

    
    /**
     * @param Logger $logger
     */
    public function __construct($logger)
    {
        $this->logger   = $logger;
        $this->drivers  = array();
    }

    /**
     * Registers push driver.
     *
     * @param string $devicePlatform
     * @param PushDriver $driver
     *
     * @return void
     */
    public function registerDriver($devicePlatform, PushDriver $driver)
    {
        $this->drivers[$devicePlatform] = $driver;
    }

    /**
     * Creates new push message.
     *
     * @param string $devicePlatform
     * @param string $devicePushId
     * @param string $applicationPackageId
     * 
     * @return PushMessage
     */
    public function createMessage($devicePlatform, $devicePushId,
        $applicationPackageId=null)
    {
        return new PushMessage(
                    $devicePlatform,
                    $devicePushId,
                    $applicationPackageId);
    }

    /**
     * Sends push message.
     *
     * @param PushMessage  $message
     *
     * @return void
     *
     * @throws PushDriverNotFoundException  If corresponding driver not found.
     * @throws PushDeliveryException        If error occurred sending message.
     */
    public function send($message)
    {
        $this->logger->debug("Sending push message: {$message}");

        $driver = $this->getDriver($message->getDevicePlatform());
        $driver->send($message);
    }

    /**
     * Returns push driver used for specified device platform.
     *
     * @param string $devicePlatform
     *
     * @return PushDriver
     * @throws PushDriverNotFoundException  If corresponding driver not found.
     */
    protected function getDriver($devicePlatform)
    {
        if (! isset($this->drivers[$devicePlatform])) {
            throw new PushDriverNotFoundException("Platform: {$devicePlatform}");
        }
        return $this->drivers[$devicePlatform];
    }


}
<?php
namespace CTLib\Component\HipChat;

use CTLib\Component\Monolog\Logger;

/**
 * Manages posting notifications to HipChat rooms.
 * @author Mike Turoff
 */
class HipChatRoomNotifier
{
    /**
     * @var string
     * Name of the HipChat group that owns the rooms.
     */
    protected $groupName;

    protected $room;

    protected $token;

    protected $disableDelivery;

    /**
     * @var Logger
     */
    protected $logger;


    /**
     * @param string $groupName
     * @parm string $room
     * @param string $token
     * @parm boolean $disableDelivery
     * @param Logger $logger
     */
    public function __construct(
        $groupName,
        $room,
        $token,
        $disableDelivery,
        Logger $logger
    ) {
        $this->groupName        = $groupName;
        $this->room             = $room;
        $this->token            = $token;
        $this->disableDelivery  = $disableDelivery;
        $this->logger           = $logger;
    }

    /**
     * Sends notification to HipChat room.
     * @param HipChatRoomNotification $notification
     * @return void
     */
    public function sendNotification(HipChatRoomNotification $notification)
    {
        if ($this->disableDelivery) {
            $this->logger->debug("HipChatRoomNotifier: delivery explicitly disabled");
        }

        $url = $this->getSendNotificationUrl();

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($notification));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_exec($curl);

        // @TODO handle response
    }

    /**
     * Returns URL to send notification.
     * @param string $roomName
     * @param string $notifierName
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getSendNotificationUrl()
    {
        if (isset($this->hipChatUrl) == false) {
            $this->hipChatUrl = "https://" . $this->groupName . ".hipchat.com"
                              . "/v2/room/" . $this->room
                              . "/notification?auth_token=" . $this->token;
        }
        return $this->hipChatUrl;
    }

}

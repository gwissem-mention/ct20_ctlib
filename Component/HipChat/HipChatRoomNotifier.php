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
     * Name of the HipChat group that owns the room.
     */
    protected $groupName;

    /**
     * @var string
     * Name of the HipChat room.
     */
    protected $roomName;

    /**
     * @var string
     * Authentication token used to post notifications.
     */
    protected $authToken;

    /**
     * @var boolean
     * Indicates whether to purposefully disable delivery.
     */
    protected $disableDelivery;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     * URL to post HipChat notification.
     */
    protected $sendUrl;


    /**
     * @param string $groupName
     * @parm string $roomName
     * @param string $authToken
     * @parm boolean $disableDelivery
     * @param Logger $logger
     */
    public function __construct(
        $groupName,
        $roomName,
        $authToken,
        $disableDelivery,
        Logger $logger
    ) {
        $this->groupName        = $groupName;
        $this->roomName         = $roomName;
        $this->authToken        = $authToken;
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
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($notification));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        $response = curl_exec($curl);

        $httpStatusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpStatusCode != 204) {
            throw new HipChatRoomNotificationDeliveryException(
                "Failed to POST notification to '{$url}'. HipChat returned HTTP {$httpStatusCode} with response: '{$response}'"
            );
        }
    }

    /**
     * Returns $groupName.
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * Returns $roomName.
     * @return string
     */
    public function getRoomName()
    {
        return $this->roomName;
    }

    /**
     * Returns $authToken.
     * @return string
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }

    /**
     * Returns URL to send notification.
     * @return string
     */
    protected function getSendNotificationUrl()
    {
        if (isset($this->sendUrl) == false) {
            // Use rawurlencode because HipChat requires '%20' for space instead
            // of '+' used by urlencode.
            $encodedRoomName = rawurlencode($this->roomName);

            $this->sendUrl = "https://" . $this->groupName . ".hipchat.com"
                              . "/v2/room/" . $encodedRoomName
                              . "/notification"
                              . "?auth_token=" . $this->authToken;
        }
        return $this->sendUrl;
    }

}

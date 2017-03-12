<?php
namespace CTLib\Component\HipChat;

use CTLib\Component\Monolog\Logger;

/**
 * Manages posting notifications to HipChat rooms.
 * @author Mike Turoff
 */
class HipChatRoomNotificationManager
{
    /**
     * @var string
     * Name of the HipChat group that owns the rooms.
     */
    protected $groupName;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     * Set of registered HipChat rooms.
     */
    protected $rooms;


    /**
     * @param string $groupName
     * @param Logger $logger
     */
    public function __construct($groupName, Logger $logger)
    {
        $this->groupName    = $groupName;
        $this->logger       = $logger;
        $this->rooms        = [];
    }

    /**
     * Registers HipChat room.
     * @param string $roomName
     * @return void
     */
    public function registerRoom($roomName)
    {
        $room = new \stdClass;
        $room->name = $roomName;
        $room->notifiers = [];

        $this->rooms[$roomName] = $room;
    }

    /**
     * Registers HipChat room notifier.
     * @param string $roomName
     * @param string $notifierName
     * @param string $authToken
     * @return void
     * @throws InvalidArgumentException
     */
    public function registerRoomNotifier($roomName, $notifierName, $authToken)
    {
        if ($this->hasRoom($roomName) == false) {
            throw new \InvalidArgumentException("No room registered to name '{$roomName}'");
        }

        $room = $this->rooms[$roomName];

        $notifier = new \stdClass;
        $notifier->name = $notifierName;
        $notifier->authToken = $authToken;

        $room->notifiers[$notifierName] = $notifier;
    }

    /**
     * Sends notification to HipChat room.
     * @param string $roomName
     * @param string $notifierName
     * @param HipChatRoomNotification $notification
     * @return void
     */
    public function sendNotification(
        $roomName,
        $notifierName,
        HipChatRoomNotification $notification
    ) {
        $url = $this->getSendNotificationUrl($roomName, $notifierName);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($notification));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_exec($curl);

        // @TODO handle response
    }

    /**
     * Returns $rooms.
     * @return array
     */
    public function getRooms()
    {
        return $this->rooms;
    }

    /**
     * Returns room names.
     * @return array
     */
    public function getRoomNames()
    {
        return array_keys($this->rooms);
    }

    /**
     * Indicates whether specified room is registered.
     * @param string $roomName
     * @return boolean
     */
    public function hasRoom($roomName)
    {
        return isset($this->rooms[$roomName]);
    }

    /**
     * Returns room configuration.
     * @param string $roomName
     * @return stdClass|null
     */
    public function getRoom($roomName)
    {
        if ($this->hasRoom($roomName) == false) {
            return null;
        }

        return $this->rooms[$roomName];
    }

    /**
     * Returns URL to send notification.
     * @param string $roomName
     * @param string $notifierName
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getSendNotificationUrl($roomName, $notifierName)
    {
        if ($this->hasRoom($roomName) == false) {
            throw new \InvalidArgumentException("No room registered to name '{$roomName}'");
        }

        $room = $this->rooms[$roomName];

        if (isset($room->notifiers[$notifierName]) == false) {
            throw new \InvalidArgumentException("No notifier registered to name '{$notifierName}' for '{$roomName}' room");
        }

        $authToken = $room->notifiers[$notifierName]->authToken;

        $url = "https://" . $this->groupName . ".hipchat.com"
             . "/v2/room/" . $roomName
             . "/notification?auth_token=" . $authToken;
        return $url;
    }

}

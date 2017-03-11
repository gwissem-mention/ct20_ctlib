<?php
namespace CTLib\Component\HipChat;

use CTLib\Component\Monolog\Logger;


class HipChatRoomNotificationManager
{



    public function __construct($groupName, Logger $logger)
    {
        $this->groupName    = $groupName;
        $this->logger       = $logger;
        $this->rooms        = [];
    }

    public function registerRoom($roomName, $authToken)
    {
        $room = new \stdClass;
        $room->name = $roomName;
        $room->authToken = $authToken;

        $this->rooms[$roomName] = $room;
    }

    public function sendNotification(
        $roomName,
        HipChatRoomNotification $notification
    ) {
        $url = $this->getSendNotificationUrl($roomName);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($notification));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_exec($curl);

        // @TODO handle response
    }

    public function getRooms()
    {
        return $this->rooms;
    }

    public function getRoomNames()
    {
        return array_keys($this->rooms);
    }

    public function hasRoom($roomName)
    {
        return isset($this->rooms[$roomName]);
    }

    protected function getSendNotificationUrl($roomName)
    {
        if ($this->hasRoom($roomName) == false) {
            throw new \InvalidArgumentException("No room registered to name '{$roomName}'");
        }

        $room = $this->rooms[$roomName];

        $url = "https://" . $this->groupName . ".hipchat.com"
             . "/v2/room/" . $roomName
             . "/notification?auth_token=" . $room->authToken;
        return $url;
    }

}

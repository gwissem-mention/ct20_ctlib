<?php
namespace CTLib\Component\HipChat;

class HipChatRoomNotification implements \JsonSerializable
{

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function jsonSerialize()
    {
        return [
            'message'           => $this->message,
            'message_format'    => 'html',
            'notify'            => false,
            'color'             => 'yellow'
        ];
    }

}

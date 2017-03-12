<?php
namespace CTLib\Component\HipChat;

/**
 * Defines a notification to be posted to a HipChat room.
 * @author Mike Turoff
 */
class HipChatRoomNotification implements \JsonSerializable
{

    /**
     * Valid message formats.
     */
    const MESSAGE_FORMATS = [
        'html',
        'plain'
    ];

    /**
     * Valid colors.
     */
    const COLORS = [
        'yellow',
        'blue',
        'red',
        'green'
    ];


    /**
     * @var string
     * The message content.
     */
    protected $message;

    /**
     * @var string
     * The message format ('html' or 'plain').
     */
    protected $messageFormat;

    /**
     * @var boolean
     * Whether to trigger HipChat notifications (i.e., icon bounce).
     */
    protected $notify;

    /**
     * @var string
     * The background color used by HipChat to display the notification.
     */
    protected $color;


    /**
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message          = $message;
        $this->messageFormat    = 'html';
        $this->notify           = false;
        $this->color            = 'yellow';
    }

    /**
     * Sets $messageFormat.
     * @param string $messageFormat
     * @return HipChatRoomNotification
     * @throws InvalidArgumentException
     */
    public function setMessageFormat($messageFormat)
    {
        if (in_array($messageFormat, self::MESSAGE_FORMATS)) {
            throw new \InvalidArgumentException('$messageFormat must be in ' . join(', ', self::MESSAGE_FORMATS));
        }

        $this->messageFormat = $messageFormat;
        return $this;
    }

    /**
     * Sets $notify.
     * @param boolean $notify
     * @return HipChatRoomNotification
     * @throws InvalidArgumentException
     */
    public function setNotify($notify)
    {
        if (is_bool($notify) == false) {
            throw new \InvalidArgumentException('$notify must be boolean');
        }

        $this->notify = $notify;
        return $this;
    }

    /**
     * Sets $color.
     * @param string $color
     * @return HipChatRoomNotification
     * @throws InvalidArgumentException
     */
    public function setColor($color)
    {
        if (in_array($color, self::COLORS)) {
            throw new \InvalidArgumentException('$color must be in ' . join(', ', self::COLORS));
        }

        $this->color = $color;
        return $this;
    }

    /**
     * Returns $message.
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Returns $messageFormat.
     * @returns string
     */
    public function getMessageFormat()
    {
        return $this->messageFormat;
    }

    /**
     * Returns $notify.
     * @return boolean
     */
    public function getNotify()
    {
        return $this->notify;
    }

    /**
     * Returns $color.
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return [
            'message'           => $this->message,
            'message_format'    => $this->messageFormat,
            'notify'            => $this->notify,
            'color'             => $this->color
        ];
    }

}

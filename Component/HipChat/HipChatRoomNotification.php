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
    const MESSAGE_FORMAT_HTML   = 'html';
    const MESSAGE_FORMAT_PLAIN  = 'plain';

    /**
     * Valid colors.
     */
    const COLOR_YELLOW  = 'yellow';
    const COLOR_BLUE    = 'blue';
    const COLOR_RED     = 'red';
    const COLOR_GREEN   = 'green';


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
        $this->messageFormat    = self::MESSAGE_FORMAT_HTML;
        $this->notify           = false;
        $this->color            = self::COLOR_YELLOW;
    }

    /**
     * Sets $messageFormat.
     * @param string $messageFormat
     * @return HipChatRoomNotification
     * @throws InvalidArgumentException
     */
    public function setMessageFormat($messageFormat)
    {
        $validFormats = [
            self::MESSAGE_FORMAT_HTML,
            self::MESSAGE_FORMAT_PLAIN
        ];

        if (in_array($messageFormat, $validFormats) == false) {
            throw new \InvalidArgumentException('$messageFormat must be valid MESSAGE_FORMAT_*');
        }

        $this->messageFormat = $messageFormat;
        return $this;
    }

    /**
     * Sets message format to HTML.
     * @return HipChatRoomNotification
     */
    public function html()
    {
        return $this->setMessageFormat(self::MESSAGE_FORMAT_HTML);
    }

    /**
     * Sets message format to plain text.
     * @return HipChatRoomNotification
     */
    public function plain()
    {
        return $this->setMessageFormat(self::MESSAGE_FORMAT_PLAIN);
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
     * Sets $notify to true.
     * @return HipChatRoomNotification
     */
    public function loud()
    {
        return $this->setNotify(true);
    }

    /**
     * Sets $notify to false.
     * @return HipChatRoomNotification
     */
    public function quiet()
    {
        return $this->setNotify(false);
    }

    /**
     * Sets $color.
     * @param string $color
     * @return HipChatRoomNotification
     * @throws InvalidArgumentException
     */
    public function setColor($color)
    {
        $validColors = [
            self::COLOR_YELLOW,
            self::COLOR_BLUE,
            self::COLOR_RED,
            self::COLOR_GREEN
        ];

        if (in_array($color, $validColors) == false) {
            throw new \InvalidArgumentException('$color must be valid COLOR_*');
        }

        $this->color = $color;
        return $this;
    }

    /**
     * Sets $color to yellow.
     * @return HipChatRoomNotification
     */
    public function yellow()
    {
        return $this->setColor(self::COLOR_YELLOW);
    }

    /**
     * Sets $color to blue.
     * @return HipChatRoomNotification
     */
    public function blue()
    {
        return $this->setColor(self::COLOR_BLUE);
    }

    /**
     * Sets $color to red.
     * @return HipChatRoomNotification
     */
    public function red()
    {
        return $this->setColor(self::COLOR_RED);
    }

    /**
     * Sets $color to green.
     * @return HipChatRoomNotification
     */
    public function green()
    {
        return $this->setColor(self::COLOR_GREEN);
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

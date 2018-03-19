<?php
namespace CTLib\Component\MSTeams;

use Monolog\Logger;

/**
 * Manages posting notifications to MSTeams connectors
 */
class MessageCardService
{
    /** @var CurlConnection */
    private $connection;

    /** @var boolean Indicates whether to purposefully disable delivery.  */
    private $disableDelivery;

    /** @var Logger */
    private $logger;

    /** @var array MessageCard */
    private $queue;

    /** @var array */
    private $sent;

    /** @var array Exception */
    private $exceptions;

    /**
     * @param string $channelId
     * @param string $authToken
     * @param string $connector
     * @param boolean $disableDelivery
     * @param Logger $logger
     */
    public function __construct(
        $channelId,
        $authToken,
        $connector,
        $disableDelivery,
        Logger $logger
    ) {
        $this->connection = new CurlConnection($channelId, $authToken, $connector);
        $this->disableDelivery  = $disableDelivery;
        $this->logger           = $logger;

        $this->queue = [];
        $this->sent = [];
        $this->exceptions = [];
    }

    /**
     * Add Message card to the queue but returns the service for chaining
     * @param string $title
     * @return MessageCardService
     */
    public function addMessageCard(...$args)
    {
        $this->createMessageCard(...$args);

        return $this;
    }

    /**
     * Adds a message card to the queue then returns that card
     * @param mixed
     * @return MessageCard
     */
    public function createMessageCard(...$args)
    {
        $firstArg = isset($args[0]) ? $args[0] : false;

        if (is_a($firstArg, 'MessageCard')) {
            $this->queue[] = $firstArg;
        } else if (is_array($firstArg)) {
            $this->queue[] = MessageCard::createFromArray($firstArg);
        } else {
            $this->queue[] = new MessageCard(...$args);
        }

        return $this->getCurrentMessageCard();
    }

    /**
     * Adds a section to the current Message Card and then returns the service
     * @param mixed
     * @return MessageCardService
     */
    public function addSection(...$args)
    {
        $this->getCurrentMessageCard()->createSection(...$args);

        return $this;
    }

    /**
     * Adds a section to the current message card then returns that section
     * @param mixed
     * @return MessageCardSection
     */
    public function createSection(...$args)
    {
        return $this->getCurrentMessageCard()->createSection(...$args);
    }

    /**
     * cycles through the queue and sends out the message cards to the
     * Microsoft Teams connector API
     */
    public function send()
    {
        if ($this->disableDelivery) {
            $this->logger->debug("MSTeams\\MessageCardService: delivery explicitly disabled");
            return true;
        }

        $success = true;
        while ($this->queue) {
            if (!$this->connection->send(array_shift($this->queue))) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @return MessageCard
     */
    public function getCurrentMessageCard()
    {
        return end($this->queue);
    }

    /**
     * @return MessageCardSection
     */
    public function getCurrentSection()
    {
        $sections = $this->getCurrentMessageCard()->getSections();
        return end($sections);
    }

    /**
     * @return CurlConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return array of exceptions
     */
    public function getExceptions()
    {
        return $this->getConnection()->getExceptions();
    }

    /**
     * @return string
     */
    public function getJson()
    {
        return json_encode($this->queue);
    }

    /**
     * @return string
     */
    public function getPrettyJson()
    {
        return json_encode($this->queue, JSON_PRETTY_PRINT);
    }
}


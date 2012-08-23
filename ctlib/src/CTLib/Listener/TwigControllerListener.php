<?php

namespace CTLib\Listener;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

class TwigControllerListener
{
    public function __construct($twigExtension)
    {
        $this->twigExtension = $twigExtension;
    }

    /**
     * Handles the event when notified or filtered.
     *
     * @param Event $event
     */
    public function onKernelController(Event $event)
    {
        if ($event->getRequestType() !== \Symfony\Component\HttpKernel\HttpKernelInterface::MASTER_REQUEST) {
            return;
        }
        $this->twigExtension->setController($event->getController());
    }
}
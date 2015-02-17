<?php
namespace CTLib\Component\Push;


/**
 * Defines API for push messaging drivers.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
interface PushDriver
{

    /**
     * Sends push message.
     *
     * @param PushMessage  $message
     *
     * @return void
     * @throws PushDeliveryException    If error occurred sending message.
     */
    public function send(PushMessage $message);


}
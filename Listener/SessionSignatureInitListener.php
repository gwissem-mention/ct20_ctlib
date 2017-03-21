<?php

namespace CTLib\Listener;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Add session signature by user browser agent and ip address,
 * prevent session hijack attacks
 *
 * @author Ziwei Ren <zren@celltrak.com>
 */
class SessionSignatureInitListener
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Logger $logger
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Callback registered to kernel.request event.
     * @param  GetResponseEvent  $event
     * @return void
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if ($event->getRequestType() != HttpKernelInterface::MASTER_REQUEST) {
            $this->logger->debug("SessionSignatureInitListener: only checks master request.");
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        if (!$session) {
            $this->logger->debug("SessionSignatureInitListener: session is not set. ");
            return;
        }

        //check if sessionSignature is set in session
        if ($session->has('sessionSignature')) {
            $this->logger->debug("SessionSignatureInitListener: session token has been set in session. ");
            return;
        }

        $signature = $this->generateSessionSignature($request);

        $session->set('sessionSignature', $signature);
    }

    /**
     * Generate session signature by using user browser info
     * and ip address, encrypt with md5 algorithm.
     *
     * @param $request
     * @return string
     */
    protected function generateSessionSignature($request)
    {
        $userAgent = $request->headers->get('User-Agent');

        $ipAddress = $request->getClientIp();

        $signature = md5($userAgent . $ipAddress);

        return $signature;
    }
}
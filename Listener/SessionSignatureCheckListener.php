<?php


namespace CTLib\Listener;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Check session signature by user browser agent and ip address,
 * prevent session hijack attacks
 *
 * @author Ziwei Ren <zren@celltrak.com>
 */
class SessionSignatureCheckListener
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
            $this->logger->debug("SessionSignatureCheckListener: only checks master request.");
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod('POST')) {
            $this->logger->debug("SessionSignatureCheckListener: only checks post request.");
            return;
        }

        $session = $request->getSession();

        if (!$session) {
            $this->logger->debug("SessionSignatureCheckListener: session is not set.");
            return;
        }

        if (!$session->has('sessionSignature')) {
            $this->logger->debug("SessionSignatureCheckListener: session signature is not set in session. ");
            return;
        }

        if ($session->get('sessionSignature') != $this->generateSessionSignature($request)) {
            $this->logger->debug("SessionSignatureCheckListener: request is not secure. ");

            //return http forbidden response 403
            $response = new Response('Access Denied!', 403);
            $event->setResponse($response);
        }
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
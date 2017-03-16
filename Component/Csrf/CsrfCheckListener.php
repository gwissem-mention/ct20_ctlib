<?php

namespace CTLib\Component\Csrf;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Cross-Site Request Forgery (CSRF) token, prevent CSRF attacks
 *
 * @author Ziwei Ren <zren@celltrak.com>
 */
class CsrfCheckListener
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
            $this->logger->debug("CsrfCheckListener: only checks master request.");
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod('POST')) {
            $this->logger->debug("CsrfCheckListener: only checks post request.");
            return;
        }

        $session = $request->getSession();

        if (!$session) {
            $this->logger->debug("CsrfCheckListener: session is not set.");
            return;
        }

        if (!$session->has('csrfToken')) {
            $this->logger->debug("CsrfCheckListener: csrf token is not set in session. ");
            return;
        }

        if (!$request->request->get('csrf_session_token')) {
            $this->logger->debug("CsrfCheckListener: form does not require csrf check. ");
            return;
        }

        if ($session->get('csrfToken') != $request->request->get('csrf_session_token')) {
            $this->logger->debug("CsrfCheckListener: request is not secure. ");
            //return http forbidden response 403
            $response = new Response("Access Denied!", 403);
            $event->setResponse($response);
        }
    }
}
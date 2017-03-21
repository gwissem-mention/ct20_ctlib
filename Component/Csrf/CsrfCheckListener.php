<?php

namespace CTLib\Component\Csrf;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
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
     * @var RouteInspector
     */
    protected $routeInspector;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var EnforceCheck
     */
    protected $enforceCheck;

    /**
     * @param RouteInspector $routeInspector
     * @param Logger $logger
     * @param boolean $enforceCheck
     */
    public function __construct($routeInspector, $logger, $enforceCheck)
    {
        $this->routeInspector   = $routeInspector;
        $this->logger = $logger;
        $this->enforceCheck = $enforceCheck;
    }

    /**
     * Callback registered to kernel.controller event.
     * @param  FilterControllerEvent  $event
     * @return void
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$this->enforceCheck) {
            $this->logger->debug("CsrfCheckListener: csrf check is not enabled.");
            return;
        }

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

        //get skip csrf check or not from route option
        $routeName = $event->getRequest()->attributes->get('_route');
        $skipCsrf = $this->routeInspector->getOption($routeName, 'skipCsrf');

        if ($skipCsrf) {
            $this->logger->debug("CsrfCheckListener: form does not require csrf check. ");
            return;
        }

        if ($session->get('csrfToken') != $request->request->get('csrf_session_token')) {
            $this->logger->debug("CsrfCheckListener: request is not secure. ");
            //return http forbidden response 403
            $controller = function() {
                return new Response('Access Denied!', 403); };

            $event->setController($controller);
            $event->stopPropagation();
        }
    }
}
<?php
namespace CTLib\Listener;

use CTLib\Component\Monolog\Logger;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Exception listener that redirects browser to another location.
 *
 * @author Mike Turoff
 */
class RedirectExceptionListener
{

    /**
     * Indicates where to redirect browser after exception.
     * @var string
     */
    protected $redirectTo;

    /**
     * Indicates whether in debug mode.
     * @var boolean
     */
    protected $debug;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Indicates whether to invalidate session after exception (debug only).
     * @var boolean
     */
    protected $invalidateSession;


    /**
     * @param string $redirectTo
     * @param boolean $debug
     * @param Logger $logger
     */
    public function __construct($redirectTo, $debug, $logger)
    {
        $this->redirectTo           = $redirectTo;
        $this->debug                = $debug;
        $this->logger               = $logger;
        $this->invalidateSession    = false;
    }

    /**
     * Sets whether the Session will be invalidated when running in non-debug
     * mode.
     * @param boolean $invalidateSession
     * @return void
     */
    public function setInvalidateSession($invalidateSession)
    {
        $this->invalidateSession = $invalidateSession;
    }

    /**
     * Kernel event callback.
     * @param GetResponseForExceptionEvent $event
     * @return void
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        // Only run when not in debug mode, for master requests, and when not
        // XHR.
        $request = $event->getRequest();

        if ($this->debug
            || $event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST
            || $request->isXmlHttpRequest()
        ) {
            return;
        }

        $exception = $event->getException();

        // Always log the exception.
        $this->logger->error((string) $exception);

        if ($this->invalidateSession) {
            $session = $request->getSession();

            if ($session) {
                $session->invalidate();
            }
        }

        $response = new RedirectResponse($this->redirectTo);
        $event->setResponse($response);
    }

}

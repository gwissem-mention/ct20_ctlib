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
     * @param Logger $logger
     */
    public function __construct($redirectTo, $logger)
    {
        $this->redirectTo           = $redirectTo;
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
        // Only run for master requests.
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
            return;
        }

        $request = $event->getRequest();
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

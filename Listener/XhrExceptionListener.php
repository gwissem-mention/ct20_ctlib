<?php
namespace CTLib\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use CTLib\Component\HttpFoundation\JsonResponse;


class XhrExceptionListener
{


    public function __construct($debug, $logger)
    {
        $this->debug = $debug;
        $this->logger = $logger;
        $this->invalidateSessionWhenNotDebug = true;
    }

    public function setInvalidateSessionWhenNotDebug($invalidateSession)
    {
        $this->invalidateSessionWhenNotDebug = $invalidateSession;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest()) {
            // This listener only handles XHRs.
            return;
        }

        // Always log the exception.
        $exception = $event->getException();
        $this->logger->addError((string) $exception);

        if ($this->debug) {
            // Return JSON-encoded object containing exception information.
            // Preserve session because debug users can continue to retry
            // without being redirected to error screen.
            $body = $this->encodeException($exception);
            $response = new JsonResponse($body, 500);
        } else {

            if ($this->invalidateSessionWhenNotDebug) {
                $session = $request->getSession();

                if ($session) {
                    $session->invalidate();
                }
            }

            $response = new Response('An error occurred', 500);
        }

        $event->setResponse($response);
    }

}

<?php
namespace CTLib\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use CTLib\Component\HttpFoundation\JsonResponse;
use CTLib\Component\Monolog\Logger;


/**
 * Exception listener designed specifically for XmlHTTPRequests (XHRs).
 *
 * @author Mike Turoff
 */
class XhrExceptionListener
{

    /**
     * @param boolean $debug
     * @param Logger $logger
     */
    public function __construct($debug, Logger $logger)
    {
        $this->debug = $debug;
        $this->logger = $logger;
        $this->invalidateSessionWhenNotDebug = true;
    }

    /**
     * Sets whether the Session will be invalidated when running in non-debug
     * mode.
     * @param boolean $invalidateSession
     * @return void
     */
    public function setInvalidateSessionWhenNotDebug($invalidateSession)
    {
        $this->invalidateSessionWhenNotDebug = $invalidateSession;
    }

    /**
     * Kernel event callback.
     * @param GetResponseForExceptionEvent $event
     * @return void
     */
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

    /**
     * Encodes Exception into JSON for use in debug mode responses.
     * @param Exception $exception
     * @return string
     */
    protected function encodeException(\Exception $exception)
    {
        $values = [
            'exception'     => true,
            'type'          => get_class($exception),
            'message'       => $exception->getMessage(),
            'stacktrace'    => $exception->getTrace()
        ];
        return json_encode($values);
    }

}

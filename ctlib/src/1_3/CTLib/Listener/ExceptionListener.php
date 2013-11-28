<?php
namespace CTLib\Listener;

use Symfony\Component\EventDispatcher\Event,
    Symfony\Component\HttpFoundation\RedirectResponse,
    Symfony\Component\HttpFoundation\Response,
    CTLib\Component\HttpFoundation\JsonResponse,
    CTLib\WebService\WebServiceException,
    CTLib\WebService\StatusCode as WebServiceStatusCode,
    CTLib\WebService\ResponseMessage as WebServiceReponseMessage;

/**
 * Default listener for exception events.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class ExceptionListener
{

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var boolean
     */
    protected $debug;

    /**
     * @var string
     */
    protected $execMode;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Session
     */
    protected $session;
    
    /**
     * @param AppKernel $kernel
     * @param Logger $logger
     * @param Session
     */
    public function __construct($kernel, $logger, $session=null)
    {
        $this->environment  = $kernel->getEnvironment();
        $this->debug        = $kernel->isDebug();
        $this->logger       = $logger;
        $this->session      = $session;

        if (method_exists($kernel, 'getRuntime')) { 
            $this->execMode = $kernel->getRuntime()->getExecMode();
        }
    }

    /**
     * Handles the event when notified or filtered.
     *
     * @param Event $event
     */
    public function onKernelException(Event $event)
    {
        $this->logger->addError((string) $event->getException());

        if ($this->execMode == 'svc') {
            if ($event->getException() instanceof WebServiceException) {
                $exception = $event->getException();
            } else {
                $exception = new WebServiceException(
                    WebServiceStatusCode::ERROR_PROCESSING_REQUEST,
                    (string) $event->getException()
                );
            }
            $msg = WebServiceReponseMessage::createForWebServiceException(
                $exception
            );

            $response = new JsonResponse(
                            $msg,
                            200,
                            array('X-Status-Code' => 200));
            $event->setResponse($response);
            $event->stopPropagation();

        } elseif ($this->debug) {
            if ($this->isXmlHttpRequest($event->getRequest())) {
                $exception = $event->getException();
                $body = array(
                    'exception'     => true,
                    'type'          => get_class($exception),
                    'message'       => $exception->getMessage(),
                    'stacktrace'    => $exception->getTrace(),
                    'redirect'      => '/error');
                $response = new JsonResponse($body, 500);
                $event->setResponse($response);
                $event->stopPropagation();
            } else {
                // Use Symfony's normal exception debug response.
            }    
        } else {
            if ($this->session) {
                $this->session->invalidate();
            }

            if ($this->isXmlHttpRequest($event->getRequest())) {
                $response = new Response('An error occurred', 500);
            } else {
                $response = new RedirectResponse('/error');                
            }
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    /**
     * Indicates whether Request originated through AJAX.
     *
     * @param Request $request
     * @return boolean
     */
    protected function isXmlHttpRequest($request)
    {
        return $request->isXmlHttpRequest()
            || $request->server->get('HTTP_USER_AGENT') == 'APP_PROXY_REQUEST';
    }
}
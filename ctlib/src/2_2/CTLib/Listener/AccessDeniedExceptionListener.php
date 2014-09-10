<?php
namespace CTLib\Listener;

use Symfony\Component\EventDispatcher\Event,
    Symfony\Component\Security\Core\Exception\AccessDeniedException,
    Symfony\Component\HttpFoundation\RedirectResponse,
    CTLib\Component\HttpFoundation\JsonResponse;

class AccessDeniedExceptionListener
{

    protected $request;
    protected $session;
    protected $debug;

    public function __construct($container, $debug)
    {
        $this->request  = $container->get('request');
        $this->session  = $container->get('session');
        $this->debug    = $debug;
    }

    /**
     * Handles the event when notified or filtered.
     *
     * @param Event $event
     */
    public function onKernelException(Event $event)
    {
        $exception = $event->getException();

        if ($exception instanceof AccessDeniedException) {

            if (! $this->debug || ! $this->request->isXmlHttpRequest()) {
                $this->session->invalidate();        
            }

            if (! $this->debug || $this->request->isXmlHttpRequest()) {
                // If we're debugging a non-AJAX request, show standard
                // Symfony exception page.  Otherwise, return 403.
                
                if ($this->request->isXmlHttpRequest()) {
                    $body = array('redirect' => '/denied');
                    $response = new JsonResponse($body, 403);
                } else {
                    $response = new RedirectResponse('/denied');    
                }
                $event->setResponse($response);
                $event->stopPropagation();
            }
        }
    }
}
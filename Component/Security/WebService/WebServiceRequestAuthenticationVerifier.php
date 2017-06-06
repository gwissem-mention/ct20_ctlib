<?php
namespace CTLib\Component\Security\WebService;

use CTLib\Component\Monolog\Logger;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;


/**
 * Attempts to authenticate every web service request (exec mode = 'svc').
 * Delegates actual authentication check to registered
 * WebServiceRequestAuthenticatorInterface instances.
 *
 * @author Mike Turoff
 */
class WebServiceRequestAuthenticationVerifier
{

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Registered set of WebServiceRequestAuthenticatorInterface instances.
     * @var array
     */
    protected $authenticators;


    /**
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger           = $logger;
        $this->authenticators   = [];
    }

    /**
     * Adds request authenticator.
     * @param WebServiceRequestAuthenticatorInterface $authenticator
     */
    public function addAuthenticator(
        WebServiceRequestAuthenticatorInterface $authenticator
    ) {
        $this->authenticators[] = $authenticator;
    }

    /**
     * Callback registered to kernel.request event.
     * @param  Event  $event
     * @return void
     */
    public function onKernelRequest(Event $event)
    {
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
            // Only care about master requests.
            return;
        }

        $request = $event->getRequest();
        $authenticator = $this->getMatchingAuthenticator($request);

        if (!$authenticator) {
            $this->logger->warn("WebServiceRequestAuthenticationVerifier: permitting request because no authenticator registered to handle request");
            return;
        }

        if ($authenticator->isAuthenticatedRequest($request) == false) {
            $response = $authenticator->getAuthenticationFailureResponse($request);
            $event->setResponse($response);
        }
    }

    /**
     * Returns request authenticator that handles request.
     * @param  Request $request
     * @return WebServiceRequestAuthenticatorInterface|null
     */
    protected function getMatchingAuthenticator(Request $request)
    {
        foreach ($this->authenticators as $authenticator) {
            if ($authenticator->isHandledRequest($request)) {
                return $authenticator;
            }
        }

        return null;
    }

}

<?php

namespace CTLib\Component\Csrf;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use CTLib\Util\RandomString;

/**
 * Listens requests to generate Cross-Site Request Forgery (CSRF) token
 *
 * @author Ziwei Ren <zren@celltrak.com>
 */
class CsrfInitListener
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
            $this->logger->debug("CsrfInitListener: only checks master request.");
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        if (!$session) {
            $this->logger->debug("CsrfInitListener: session is not set. ");
            return;
        }

        //check if csrfToken is set in session
        if ($session->has('csrfToken')) {
            $this->logger->debug("CsrfInitListener: csrf token has been set in session. ");
            return;
        }

        $csrfToken = $this->generateToken();
        $session->set('csrfToken', $csrfToken);
    }

    /**
     * Generate CSRF token with random string
     *
     * @return mixed
     * @throws \Exception
     */
    protected function generateToken()
    {
        $randStr = new RandomString(
            RandomString::TYPE_NUMBER |
            RandomString::TYPE_ALPHA_ALL |
            RandomString::TYPE_SYMBOL_COMPLEX
        );

        $csrfToken = $randStr->create(50);

        return $csrfToken;
    }
}
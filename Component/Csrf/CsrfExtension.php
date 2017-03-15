<?php
namespace CTLib\Component\Csrf;

/**
 * Cross-Site Request Forgery (CSRF) prevention twig extension
 *
 * @author Ziwei Ren <zren@celltrak.com>
 */
class CsrfExtension extends \Twig_Extension
{
    /**
     * Stores session object
     *
     * @var \Symfony\Component\HttpFoundation\Session
     */
    protected $session;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Session $session
     * @param Logger $logger
     */
    public function __construct($session, $logger)
    {
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'csrf_extension';
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return [
            'csrfToken' => new \Twig_Function_Method($this, 'getCsrfToken')
        ];
    }

    /**
     * Get CSRF token
     * @return string
     * @throws \Exception
     */
    public function getCsrfToken()
    {
        if (!$this->session) {
            $this->logger->debug("CsrfExtension: session is not set.");
            return;
        }

        if (!$this->session->has('csrfToken')) {
            $this->logger->debug("CsrfExtension: 'csrfToken' is not set in session.");
            return;
        }

        $csrfToken = $this->session->get('csrfToken');

        return "<script type='text/javascript'>
                $('<input>').attr({
                    type: 'hidden',
                    name: 'csrf_session_token',
                    value: '$csrfToken'
                }).appendTo('form');</script>";
    }
}
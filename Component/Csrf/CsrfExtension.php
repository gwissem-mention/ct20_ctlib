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
            'csrfToken' => new \Twig_Function_Method($this, 'getCsrfToken'),
            'csrfTokenField' => new \Twig_Function_Method($this, 'addCsrfTokenField'),
            'addCsrfTokenFields' => new \Twig_Function_Method($this, 'addCsrfTokenFields'),
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

        return $this->session->get('csrfToken');
    }

    /**
     * Add CSRF token hidden field
     * @return string
     * @throws \Exception
     */
    public function addCsrfTokenField()
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

        return '<input type="hidden" name="csrf_session_token" value="'. $csrfToken . '" />';
    }

    /**
     * Add CSRF token hidden field to all multiple forms
     * @return string
     * @throws \Exception
     */
    public function addCsrfTokenFields()
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
                if (!$('form').hasClass('skip_csrf')) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'csrf_session_token',
                        value: '$csrfToken'
                    }).appendTo('form');

                };</script>";
    }
}
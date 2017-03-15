<?php
namespace CTLib\Component\Security;

use CTLib\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
/**
 * Facilitates processing --details here
 *
 * @author seanhunter <shunter@celltrak.com>
 * Date: 2017-03-14
 */
// TODO - check if we can use route options (on OTP/CTP) to be more specific
// for the use of this service

class InputSanitizationListener
{
    // check for common script tags
    const BLACKLIST_SCRIPT_TAGS = '/<\/*(?:applet|b(?:ase|ody|gsound|link)|href|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)+>/';
    const BLACKLIST_HTML_TAGS   = '/<*(?:ht(?:tp|tps|ml))\:/';

    const BLACKLIST_CHECK_LIST = [
            self::BLACKLIST_HTML_TAGS,
            self::BLACKLIST_SCRIPT_TAGS
        ];

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string $redirect
     */
    protected $redirect;

    /**
     * @param Logger $logger
     * @param string $redirect
     */
    public function __construct($logger, $redirect)
    {
        $this->logger = $logger;
        $this->redirect = $redirect;
    }

    /**
     * Function to check the request for
     * form POST values when available, and check against XSS.
     *
     * @param GetResponseEvent $event
     *
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST')) {
            // only checking for POST requests
            return;
        }
        $params = $request->request->all();
        // check fields - from request - query
        $result = $this->checkValues($params);

        if (!$result) {
            // validation has failed, redirect to error page
            if ($request->isXmlHttpRequest()) {
                $body = ['redirect' => $this->redirect];
                $response = new JsonResponse($body, 400);
            } else {
                $response = new RedirectResponse($this->redirect);
            }
            $event->setResponse($response);

            $event->stopPropagation();
        }
    }

    /**
     * Function to process the array of field/values from the request
     *
     * @param array $values
     *
     * @return bool True/False (pass/fail)
     */
    protected function checkValues(array $values) {
        foreach ($values as $field=>$value) {
            if (!$value) {
                // empty value - default to pass
                continue;
            }

            if (is_array($value)) {
                $result = $this->checkValues($value);
                if (!$result) {
                    return false;
                }
            } else {
                // always check for these
                foreach (self::BLACKLIST_CHECK_LIST as $checkItem) {
                    // Fail if match found
                    if (preg_match($checkItem, $value)) {
                        $this->logger->warn("Post failed on validation check - {$field}: {$value}");
                        return false;
                    }
                }
            }
        }
        return true;
    }

}
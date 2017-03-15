<?php
namespace CTLib\Component\Security;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Response;
/**
 * Facilitates processing --details here
 *
 * @author seanhunter <shunter@celltrak.com>
 * Date: 2017-03-14
 */
class InputSanitizationListener
{
    // check for common script tags
    const BLACKLIST_SCRIPT_TAGS = '/<\/*(?:applet|b(?:ase|ody|gsound|link)|href|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)+>/';
    const BLACKLIST_HTML_TAGS   = '/<*(?:ht(?:tp|tps|ml))\:/';

    protected $blacklistChecks;

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

        // expandable list of blacklist checks
        $this->blacklistChecks = [
            self::BLACKLIST_HTML_TAGS,
            self::BLACKLIST_SCRIPT_TAGS
        ];
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
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest()) {
            // This listener only handles XHRs.
            return;
        }

        // check POST fields
        $result = $this->cycleValueArray($_POST);

        if (!$result) {
            $response = new Response('', 400);
            $event->setResponse($response);
        }
    }

    protected function cycleValueArray($valueArray) {
        foreach ($valueArray as $field=>$value) {
            if (!$value) {
                // empty value - default to pass
                continue;
            }

            if (is_array($value)) {
                $result = $this->cycleValueArray($value);
                if (!$result) {
                    return false;
                }
            } else {
                // always check for these
                foreach ($this->blacklistChecks as $checkItem) {
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
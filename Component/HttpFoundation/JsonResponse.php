<?php
/**
 * CellTrak 2.x Project.
 *
 * @package CTLib
 */

namespace CTLib\Component\HttpFoundation;

/**
 * JsonResponse class.
 *
 * Converts arrays and objects into a HTTP Response.
 */
class JsonResponse extends \Symfony\Component\HttpFoundation\Response
{

    /**
     * Constructor.
     *
     * @param mixed   $content
     * @param integer $httpResponseCode
     * @param array   $headers
     */
    public function __construct(
        $content,
        $httpResponseCode = 200,
        $headers = []
    ) {
        if (!is_string($content)) {
            if (is_object($content) && method_exists($content, 'toJson')) {
                // Using custom toJson method. This dates back from when we
                // didn't have access to JsonSerializable interface. The use of
                // #toJson is deprecated; implement JsonSerializable instead.
                $json = $content->toJson();
            } else {
                $json = json_encode($content);

                if ($json === false) {
                    $errorCode  = json_last_error();
                    $errorMsg   = json_last_error_msg();
                    $content    = print_r($content, true);
                    throw new \RuntimeException("Failed to JSON-encode response content with error [{$errorCode}] '{$errorMsg}'.\n\n{$content}");
                }
            }
        }

        $headers['Content-type'] = 'application/json';

        parent::__construct($json, $httpResponseCode, $headers);
    }

}

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
     */
    public function __construct($content, $httpResponseCode=200)
    {
        if (! is_string($content)) {
            if (is_object($content) && method_exists($content, 'toJson')) {
                $content = $content->toJson();
            } else {
                $content = json_encode($content);
            }
        }

        parent::__construct(
            $content,
            $httpResponseCode,
            array('Content-type' => 'application/json')
        );
    }

}

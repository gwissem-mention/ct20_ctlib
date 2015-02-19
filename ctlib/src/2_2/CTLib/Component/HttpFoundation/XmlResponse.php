<?php
/**
 * CellTrak 2.x Project.
 *
 * @package CTLib
 */

namespace CTLib\Component\HttpFoundation;

/**
 * XML class.
 *
 * Creates Response with content of header type XML.
 */
class XmlResponse extends \Symfony\Component\HttpFoundation\Response
{

    /**
     * Constructor.
     *
     * @param mixed   $content
     * @param integer $httpResponseCode
     * @param array   $headers  
     */
    public function __construct($content, $httpResponseCode=200, $headers=[])
    {
        parent::__construct(
            $content,
            $httpResponseCode, 
            $headers + ['Content-type' => 'text/xml']    
        );
    }

}

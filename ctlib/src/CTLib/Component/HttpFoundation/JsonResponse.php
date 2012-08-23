<?php
namespace CTLib\Component\HttpFoundation;

class JsonResponse extends \Symfony\Component\HttpFoundation\Response
{
    
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
<?php
namespace CTLib\Component\HttpFoundation;

class WebServiceRequest extends \Symfony\Component\HttpFoundation\Request
{
    
    protected $message;
    protected $site;


    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setSite($site)
    {
        $this->site = $site;
    }

    public function getSite()
    {
        return $this->site;
    }


    

}
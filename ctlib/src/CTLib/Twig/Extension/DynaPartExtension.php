<?php
namespace CTLib\Twig\Extension;

use CTLib\Util\Arr;

class DynaPartExtension extends \Twig_Extension
{
    /**
     * storage for exchanging data between frontend controller and dynapart controller
     *
     * @var array 
     *
     */
    private $parameterBag = array();


    public function __construct($cacheHelper, $container)
    {
        $this->cacheHelper  = $cacheHelper;
        $this->request      = $container->get('request');
    }


    public function setParameter($dynapartId, $key, $value)
    {
        if (Arr::findByKeyChain($this->parameterBag, $dynapartId.".".$key, null) != null) {
            throw new \Exception("conflict found!");
        }

        $this->parameterBag[$dynapartId][$key] = $value;
    }

    public function getParameter($dynapartId, $key)
    {
        return Arr::findByKeyChain($this->parameterBag, $dynapartId.".".$key, null);
    }

    /**
     * return a tag name 'dynapart'
     *
     * @return string tag name
     *
     */
    public function getName()
    {
        return 'dynapart';
    }

    /**
     * create parse
     *
     * @return array array of parser
     *
     */
    public function getTokenParsers()
    {
        return array(new DynaPartTokenParser($this->translator));
    }

    public function getCache()
    {
        return $this->cacheHelper;
    }

    public function getRequest()
    {
        return $this->request;
    }
}

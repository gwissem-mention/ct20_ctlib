<?php
namespace CTLib\Tests;

require_once __DIR__."/../../../../app/AppKernel.php";

use CTLib\Component\Runtime\Runtime;

/**
 * Example base class for testing DIC services.
 */
class ServiceTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    protected $_container;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->_container = $kernel->getContainer();
        parent::__construct();
    }

    /**
     * Return the requested service.
     *
     * @param string $service
     *
     * @return StdClass  The service requested.
     */
    protected function get($service)
    {
        return $this->_container->get($service);
    }
}

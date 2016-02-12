<?php
namespace CTLib\Tests;

require_once __DIR__."/../../../../app/AppKernel.php";

use CTLib\Component\Runtime\Runtime;

/**
 * Example base class for testing DIC services.
 *
 * The config_test.yml file needs to be populated thusly...
 * doctrine:
 *   dbal:
 *     driver:   pdo_sqlite
 *     path:     :memory:
 *     memory:   true
 *   orm:
 *     auto_generate_proxy_classes: true
 *     auto_mapping: true
 *
 * Often we deal with testing code which works with database. For this
 * purpose we should use Doctrine Fixtures Bundle and create some fixture
 * classes. But we need to load those fixtures before every test, to make
 * sure, that our tests are running in isolated environement. We can use
 * ModelTestCase, like this...
 */
class ModelTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Console\Application
     */
    protected $_application;

    /**
     * Get the container.
     *
     * @return \Symfony\Component\DependencyInjection\Container
     */
    public function getContainer()
    {
        return $this->_application->getKernel()->getContainer();
    }

    /**
     * Set up for test.
     *
     * The runConsole functionality is for recreating the database
     * and populating the fixtures data.  This could be replaced with
     * something else.
     *
     * @return void
     */
    public function setUp()
    {
        $kernel = new \AppKernel("test", true);
        $kernel->boot();
        $this->_application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $this->_application->setAutoExit(false);
        $this->runConsole("doctrine:schema:drop", array("--force" => true));
        $this->runConsole("doctrine:schema:create");
        $this->runConsole("doctrine:fixtures:load",
            array("--fixtures" => __DIR__ . "/../DataFixtures")
        );
    }

    /**
     * Run a console command.
     *
     * @param string $command
     * @param array  $options
     *
     * @return results
     */
    protected function runConsole($command, array $options = array())
    {
        $options["-e"] = "test";
        $options["-q"] = null;
        $options = array_merge($options, array('command' => $command));
        return $this->_application->run(new \Symfony\Component\Console\Input\ArrayInput($options));
    }
}

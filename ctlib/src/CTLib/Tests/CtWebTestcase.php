<?php

namespace CTLib\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Tools\SchemaTool;
use CTLib\Component\Runtime\Runtime;

/**
 * CellTrak custom web testcase class.
 *
 * @uses WebTestCase
 */
class CtWebTestcase extends WebTestCase
{
    /**
     * @var mixed
     */
    protected $client;

    /**
     * @var mixed
     */
    protected $container;

    /**
     * @var mixed
     */
    protected $doctrine;

    /**
     * @var mixed
     */
    protected $em;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var DataLoader
     */
    private $dataLoader;

    /**
     * Creates a Kernel.
     *
     * @param array $config An array of options
     *
     * @return HttpKernelInterface A HttpKernelInterface instance
     */
    static protected function createKernel(array $config=array(), $site=null, $session=array())
    {
        if (null === static::$class) {
            static::$class = static::getKernelClass();
        }

        return new static::$class(
            //new \CTLib\Component\Runtime\Runtime($config, $site)
            //\CTLib\Component\Runtime\Runtime::createForGateway()
            new Runtime(
                $_SERVER['SYMFONY__CT__ENVIRONMENT'],
                $_SERVER['SYMFONY__CT__DEBUG'],
                Runtime::EXEC_MODE_CONSOLE,
                $_SERVER['SYMFONY__CT__BRAND_ID'],
                $_SERVER['SYMFONY__CT__BRAND_NAME'],
                $site,
                $_SERVER['SYMFONY__CT__APP_VERSION']
            )
        );
    }

    /**
     * Creates a Client.
     *
     * @param array   $options An array of options to pass to the createKernel class
     * @param array   $server  An array of server parameters
     *
     * @return Client A Client instance
     */
    static protected function createClient(array $options = array(), array $server = array(), $session=array())
    {
        static::$kernel = static::createKernel($options);
        static::$kernel->boot();

        foreach ($session as $key => $value) {
            $_SESSION['_symfony2']['attributes'][$key] = $value;
        }

        $client = static::$kernel->getContainer()->get('test.client');
        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Override default setUp.
     *
     * @return void
     */
    protected function setUp() {
        self::gatewaySetUp();
    }

    /**
     * Test setUp function for the GatewayBundle.
     *
     * @return void
     */
    protected function gatewaySetUp()
    {
        parent::setUp();

        $this->client = $this->createClient(
            array('debug' => true),
            array()
        );
        $this->container = $this->client->getContainer();
        $this->doctrine = $this->container->get('doctrine');
        $this->em = $this->doctrine->getEntityManager();
        $this->client->getCookieJar()
            ->set(new \Symfony\Component\BrowserKit\Cookie(session_name(), true));
        $this->session = $this->container->get('session');

        //$schemaTool = new SchemaTool($this->em);

        //$mdf = $this->em->getMetadataFactory();
        //$classes = $mdf->getAllMetadata();

        //$schemaTool->dropDatabase();
        //$schemaTool->createSchema($classes);

        //$this->dataLoader = new DataLoader($this->em);
        //$this->dataLoader->loadAll();
    }

    /**
     * Test setUp function for the AppBundle.
     *
     * @return void
     */
    protected function appSetUp()
    {
        //parent::setUp();

        static::$kernel = self::createKernel(
            array(
                'debug'         => true,
                'siteId'        => 'dev200',
                'appVersion'    => '2_0',
            ),
            (object) array('id' => $_SERVER['SYMFONY__CT__SITE_ID']),
            array(
                'siteId'        => 'dev200',
                'appVersion'    => '2_0',
                'brandId'       => 'CT',
                'brandName'     => 'CellTrak',
                'userId'        => 1,
                'memberId'      => 1,
                'memberTypeId'  => 'ADMIN',
                'siteApiSecret' => 'walawalabingbang',
                'locale'        => 'en_US',
            )
        );
        static::$kernel->boot();

        $this->container = static::$kernel->getContainer();
        $this->client = $this->container->get('test.client');
        //$this->client->setServerParameters($server);
        $this->doctrine = $this->container->get('doctrine');
        $this->em = $this->doctrine->getEntityManager();
        $this->client->getCookieJar()
            ->set(new \Symfony\Component\BrowserKit\Cookie(session_name(), true));
        $this->session = $this->container->get('app_session');
        $this->em->setQueryMetaMapCache($this->container->get('query_meta_map_cache'));
    }
}

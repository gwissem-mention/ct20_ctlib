<?php
namespace CTLib\Tests;

require_once __DIR__."/../../../../app/AppKernel.php";

use CTLib\Component\Runtime\Runtime;

/**
 * Example Base class for testing entities.
 */
class EntityTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CTLib\Component\Doctrine\ORM\EntityManager
     */
    protected $_entityManager = null;

    public function setUp()
    {
        $conn = \Doctrine\DBAL\DriverManager::getConnection(array(
            'driver' => 'pdo_sqlite',
            'memory' => true
        ));

        $config = new \Doctrine\ORM\Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setProxyNamespace('BrillanteTests\Entities');
        $config->setMetadataDriverImpl(new AnnotationDriver(new IndexedReader(new AnnotationReader())));
        $config->setQueryCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        $params = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        $this->_entityManager =  \Doctrine\ORM\EntityManager::create($params, $config);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->_entityManager);

        $classes = array(
            $this->_entityManager->getClassMetadata("\Brillante\SampleBundle\Entity\Account"),
            $this->_entityManager->getClassMetadata("\Brillante\SampleBundle\Entity\User"),

        );

        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}

<?php 
namespace CTLib\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition,
    Symfony\Component\DependencyInjection\Reference,
    Symfony\Component\Finder\Finder;


/**
 * Dependency injection extension designed to compile iOS push driver service.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class IOSPushDriverExtension
{

    /**
     * Builds service definition.
     *
     * @return Definition
     */
    public function buildDefinition(
                        $prodServiceUrl,
                        $devServiceUrl,
                        $certDir,
                        $certPass)
    {
        $def = new Definition(
                    'CTLib\Component\Push\Driver\IOSPushDriver',
                    array(new Reference('logger')));

        $this
            ->addPushServices(
                $def,
                $prodServiceUrl,
                $devServiceUrl,
                $certDir,
                $certPass);

        return $def;
    }

    /**
     * Adds one iOS push service per cert in directory.
     *
     * @param Definition $def
     * @param string $prodServiceUrl
     * @param string $devServiceUrl
     * @param string $certDir
     * @param string $certPass
     *
     * @return void
     */
    protected function addPushServices(
                        $def,
                        $prodServiceUrl,
                        $devServiceUrl,
                        $certDir,
                        $certPass)
    {
        if (! is_dir($certDir)) { return; }

        $finder = new Finder;
        $finder->files()->name('*.pem')->in($certDir);

        foreach ($finder as $file) {
            // Each cert is named by its package id.
            $packageId = $file->getBasename('.pem');

            // Certs indicate whether they use iOS production or development
            // service URL based on token in package id.
            if (strpos($packageId, 'production')) {
                $url = $prodServiceUrl;
            } else {
                $url = $devServiceUrl;
            }

            $args = array($packageId, $url, $file->getRealPath(), $certPass);
            $def->addMethodCall('addService', $args);
        }
    }
    

}
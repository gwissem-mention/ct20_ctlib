<?php
namespace CTLib;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use CTLib\DependencyInjection\LoggerCompilerPass;
use CTLib\DependencyInjection\EntityManagerCompilerPass;
use CTLib\DependencyInjection\CachedComponentCompilerPass;
use CTLib\DependencyInjection\ActionLoggerCompilerPass;
use CTLib\DependencyInjection\WebServiceRequestAuthenticatorCompilerPass;
use CTLib\DependencyInjection\GarbageCollectionCompilerPass;
use CTLib\DependencyInjection\ProcessLockCompilerPass;

class CTLib extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new LoggerCompilerPass);
        $container->addCompilerPass(new EntityManagerCompilerPass);
        $container->addCompilerPass(new CachedComponentCompilerPass);
        $container->addCompilerPass(new ActionLoggerCompilerPass);
        $container->addCompilerPass(new WebServiceRequestAuthenticatorCompilerPass);
        $container->addCompilerPass(new GarbageCollectionCompilerPass);
        $container->addCompilerPass(new ProcessLockCompilerPass);
    }

	public static function getCTLibVersion()
	{
		return basename(dirname(dirname(__FILE__)));
	}
}

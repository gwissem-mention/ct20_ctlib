<?php
namespace CTLib;

use Symfony\Component\HttpKernel\Bundle\Bundle,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    CTLib\DependencyInjection\LoggerCompilerPass,
    CTLib\DependencyInjection\EntityManagerCompilerPass;
    

class CTLib extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new LoggerCompilerPass);
        $container->addCompilerPass(new EntityManagerCompilerPass);
    }

	public static function getCTLibVersion()
	{
		return basename(dirname(dirname(__FILE__)));
	}
}

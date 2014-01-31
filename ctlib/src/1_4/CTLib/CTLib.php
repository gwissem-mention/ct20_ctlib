<?php
namespace CTLib;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CTLib extends Bundle
{
	public static function getCTLibVersion()
	{
		return basename(dirname(dirname(__FILE__)));
	}
}

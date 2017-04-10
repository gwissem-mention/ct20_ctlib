<?php
namespace CTLib\Component\ProcessLock;


interface ProcessLockConsumerInterface
{

    public function getLockName();

    public function getLockIdPattern();

    public function getLockTtl();

}

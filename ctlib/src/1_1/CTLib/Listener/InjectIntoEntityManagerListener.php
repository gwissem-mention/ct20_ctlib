<?php
namespace CTLib\Listener;

use Symfony\Component\EventDispatcher\Event,
    Symfony\Component\HttpKernel\HttpKernelInterface;


class InjectIntoEntityManagerListener
{
    
    protected $entityManager;
    protected $queryMetaMapCache;
    

    public function __construct($entityManager, $queryMetaMapCache)
    {
        $this->entityManager        = $entityManager;
        $this->queryMetaMapCache    = $queryMetaMapCache;
    }

    public function onKernelRequest(Event $event)
    {
        $this->entityManager->setQueryMetaMapCache($this->queryMetaMapCache);
    }

}
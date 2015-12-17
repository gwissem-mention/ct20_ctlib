<?php
namespace CTLib\Component\HttpFoundation;

use CTLib\Util\Arr;

/**
 * Extends Symfony NativeSessionStorage to prevent errors when session already
 * started by app.php.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class SessionStorage
        extends \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage
{
    
    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        if (! @session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }

        $this->loadSession();
        if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
            // This condition matches only PHP 5.3 with internal save handlers
            $this->saveHandler->setActive(true);
        }

        return true;
    }

}
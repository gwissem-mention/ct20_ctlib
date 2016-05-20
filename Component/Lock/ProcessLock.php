<?php

namespace CTLib\Component\Lock;

/**
 * Created by PhpStorm.
 * User: norys
 * Date: 5/12/16
 * Time: 7:49 AM
 */

class ProcessLock
{
    protected $lockValue;
    protected $lockTTL;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var RedisManager
     */
    protected $redisManager;

    /**
     * @param string $namespace
     * @param RedisManager $redisManager
     */
    public function __construct(
        $namespace,
        $redisManager)
    {
        $this->namespace = $namespace;
        $this->redisManager = $redisManager;
    }
    
    /* Generates a lock key (at the site-level).
     *
     * @param string $id    Lock identifier.
     * @return string
     */
    protected function generateLockKey($id)
    {
        if ($this->namespace) {
            return "proclock:{$this->namespace}:{$id}";
        }
        else {
            return "proclock:{$id}";
        }
    }

    /**
     * Acquires site-level lock.
     *
     * @param string $id    Lock identifier.
     * @param integer $timeout  If <= 0, lock will not have timeout.
     * @return boolean  Returns TRUE if lock acquired. FALSE if not.
     * @throws \Exception
     */
    public function acquireLock($id, $timeout=0)
    {
        $this->lockTTL = $timeout;
        // generate the key for the lock
        $key = $this->generateLockKey($id);
        // generate a unique string for the lock value
        $this->lockValue = uniqid();

        $args = ["nx", "ex"=>$this->lockTTL];
        // set key if key does not exist
        $isAcquired = $this->redisManager->set($key, $this->lockValue, $args);

        // check if the lock has been acquired
        if ($isAcquired) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Refreshes site-level lock
     *
     * @param string $id            Lock identifier
     * @return boolean  Returns TRUE if lock refreshed. FALSE if not.
     */
    public function refreshLock($id)
    {
        $ls = "if redis.call('GET', KEYS[1]) == ARGV[1] then\n"
            . "    redis.call('SETEX', KEYS[1], ARGV[2], ARGV[1])\n"
            . "    return 1\n"
            . "else\n"
            . "    return 0\n"
            . "end";

        // keys and args for script
        $keys = [$this->generateLockKey($id)];
        $args = [$this->lockValue, $this->lockTTL];

        return $this->redisManager->runScript($ls, $keys, $args);
    }

    /**
     * Releases site-level lock.
     *
     * @param string $id    Lock identifier.
     * @return boolean  Returns TRUE if lock released. FALSE if not.
     */
    public function releaseLock($id)
    {
        $ls = "if redis.call('GET', KEYS[1]) == ARGV[1] then\n"
            . "    redis.call('DEL', KEYS[1])\n"
            . "    return 1\n"
            . "else\n"
            . "    return 0\n"
            . "end";

        // keys and args for script
        $keys = [$this->generateLockKey($id)];
        $args = [$this->lockValue];

        return $this->redisManager->runScript($ls, $keys, $args);
    }

    /**
     * Indicates whether site-level lock already exists.
     *
     * @param string $id    Lock identifier.
     *
     * @return boolean
     */
    public function isLocked($id)
    {
        // generate the key for the lock
        $key = $this->generateLockKey($id);

        // retrieve value from key
        $value = $this->redisManager->get($key);

        // if a value exists return true
        if ($value) {
            return true;
        } else {
            return false;
        }
    }


}
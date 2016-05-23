<?php

namespace CTLib\Component\ProcessLock;

/**
 * ProcessLock manages the acquisition, refreshing, releasing, and status
 * checking of site-level locks.
 *
 * @author Christopher Norys <CNorys@celltrak.com>
 */
class ProcessLock
{
    /**
     * An array of key-value pairs for locks
     *
     * @var array
     */
    protected $lockValues = [];

    /**
     * @var CellTrakRedis
     */
    protected $redisManager;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @param string $namespace
     * @param CellTrakRedis $redisManager
     */
    public function __construct(
        $redisManager,
        $namespace = null
    ) {
        $this->redisManager = $redisManager;
        $this->namespace = $namespace;
    }

    function __destruct() {

        // if lockValues is empty, return
        if (count($this->lockValues) <= 0) {
            return;
        }

        $luaScript =
              "local values = redis.call('MGET', unpack(KEYS));"
            . "local keys = {};"
            . "for i=1, #values do"
            . "    if values[i] ~= nil and values[i] == ARGV[i] then"
            . "        keys[#keys + 1] = KEYS[i];"
            . "    end "
            . "end "
            . "if #keys > 0 then"
            . "    redis.call('DEL', unpack(keys));"
            . "end";

        // get order arrays of keys and values
        $keys = array_keys($this->lockValues);
        $values = array_values($this->lockValues);

        $this->redisManager->runScript($luaScript, $keys, $values);
    }
    /**
     * Acquires site-level lock.
     *
     * @param string $id    Lock identifier.
     * @param integer $timeout  If <= 0, lock will not have timeout.
     * @return boolean  Returns TRUE if lock acquired. FALSE if not.
     * @throws \Exception
     */
    public function acquireLock($id, $timeout = 1)
    {
        if ($timeout < 1) {
            throw new \InvalidArgumentException("ProcessLock: (acquireLock) minimum timeout value of 1 expected for id {$id}: timeout of {$timeout} given instead");
        }

        // generate the key for the lock
        $key = $this->generateLockKey($id);
        // generate a unique string for the lock value
        $value = uniqid();

        $args = ["nx", "ex"=> $timeout];
        // set key if key does not exist
        $isAcquired = $this->redisManager->set($key, $value, $args);

        // check if the lock has been acquired
        if ($isAcquired) {
            $this->lockValues[$key] = $value;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Refreshes site-level lock
     *
     * @param string $id            Lock identifier
     * @param integer $timeout  If <= 0, lock will not have timeout.
     * @return boolean  Returns TRUE if lock refreshed. FALSE if not.
     */
    public function refreshLock($id, $timeout = 1)
    {
        if ($timeout < 1) {
            throw new \InvalidArgumentException("ProcessLock: (refreshLock) minimum timeout value of 1 expected for id {$id}: timeout of {$timeout} given instead");
        }
        
        // generate key name
        $key = $this->generateLockKey($id);

        // if there is no key-value pair for $key, return false
        if (! isset($this->lockValues[$key])) {
            return false;
        }

        $luaScript =
              "if redis.call('GET', KEYS[1]) == ARGV[1] then"
            . "    redis.call('SETEX', KEYS[1], ARGV[2], ARGV[1]);"
            . "    return 1;"
            . "else"
            . "    return 0;"
            . "end";

        // get the key-value pair from $lockValues
        $value = $this->lockValues[$key];

        $isRefreshed = $this->redisManager
            ->runScript($luaScript, [$key], [$value, $timeout]);

        if ($isRefreshed) {
            return true;
        } else {
            // remove key-value in lock array
            unset($this->lockValues[$key]);

            return false;
        }
    }

    /**
     * Releases site-level lock.
     *
     * @param string $id    Lock identifier.
     * @return boolean  Returns TRUE if lock released. FALSE if not.
     */
    public function releaseLock($id)
    {
        // generate key name
        $key = $this->generateLockKey($id);

        // if there is no key-value pair for $key, return false
        if (! isset($this->lockValues[$key])) {
            return false;
        }

        $luaScript =
              "if redis.call('GET', KEYS[1]) == ARGV[1] then"
            . "    redis.call('DEL', KEYS[1]);"
            . "    return 1;"
            . "else"
            . "    return 0;"
            . "end";

        // get the lock key value from $lockValues
        $value = $this->lockValues[$key];

        // remove key-value in lock array
        unset($this->lockValues[$key]);

        return $this->redisManager->runScript($luaScript, [$key], [$value]);
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
        return $this->redisManager->exists($key);
    }

    /**
     * Indicates whether the site-level lock is owned.
     *
     * @param string $id    Lock identifier.
     *
     * @return boolean
     */
    public function ownsLock($id)
    {
        // generate the key for the lock
        $key = $this->generateLockKey($id);

        // if there is no key-value pair for $key, return false
        if (! isset($this->lockValues[$key])) {
            return false;
        }

        // use the key to get the value from $lockValues
        $localValue = $this->lockValues[$key];

        // retrieve value from key
        $redisValue = $this->redisManager->get($key);

        if ($redisValue == $localValue) {
            return true;
        } else {
            // remove key-value in lock array
            unset($this->lockValues[$key]);

            return false;
        }
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
        } else {
            return "proclock:{$id}";
        }
    }
}
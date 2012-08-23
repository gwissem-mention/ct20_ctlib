<?php
namespace CTLib\Util;

use CTLib\Util\Arr;

/**
 * Mutex is used to handle concurrency issue in command programs
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
class Mutex
{
    const NONBLOCKING_LIVE_TIMESTAMP_KEY    = "liveTimestamp";
    const NONBLOCKING_LOCK_ID_KEY           = "lockid";

    /**
     * @var string
     */
    private $mutexDir;

    /**
     * @var array
     */
    private $blockingMutexes;

    /**
     * @var array
     */
    private $nonBlockingMutexes;


    /**
     * Constructor to create a mutex
     * 
     * @param Kernel $kernel Kernel object
     */
    function __construct($kernel)
    {
        $this->mutexDir             = $kernel->getMutexDir();
        $this->blockingMutexes      = array();
        $this->nonBlockingMutexes   = array();
    }

    function __destruct()
    {
        $this->unlockAll();
    }

    /**
     * blockingLock will put an lock on mutex file. it will 
     * stall the process until blockingUnlock is called.
     * 
     * @param string $mutexName the name of mutex
     * @return void
     */
    public function blockingLock($mutexName)
    {
        $mutexPath = $this->mutexPath($mutexName, 'blocking');
        $mutexFile = $this->createMutexFile($mutexPath, 'w+');

        if (! flock($mutexFile, \LOCK_EX)) {
            throw new \Exception('Could not lock mutex file');
        }
        $this->blockingMutexes[$mutexName] = array($mutexFile, $mutexPath);
    }

    /**
     * blockingUnlock release the lock been put by blockingLock
     *
     * @param string $mutexName the name of mutex
     * @return void
     */
    public function blockingUnlock($mutexName)
    {
        if (! isset($this->blockingMutexes[$mutexName])) {
            throw new \Exception("Blocking mutex not found for '{$mutexName}'");
        }
        
        list($mutexFile, $mutePath) = $this->blockingMutexes[$mutexName];

        if (flock($mutexFile, \LOCK_UN) === false) {
            throw new \Exception('Could not unlock mutex file');
        }

        fclose($mutexFile);
        @unlink($mutexPath);
        unset($this->blockingMutexes[$mutexName]);
    }

    /**
     * nonBlockingLock will put a loc on mutex, unlike blockingLock it will not hang process.
     * if there is no lock existing for this mutex before, it returns true. 
     * if any other process locked this mutex before, it return false
     *
     * @param string $mutexName the name of mutex
     * @param int $duration determine how long this lock can last in second
     * @return bool true: locking successful, 
     *              false: this mutex has already been locked by other process.
     *
     */
    public function nonBlockingLock($mutexName, $duration)
    {
        $mutexPath = $this->mutexPath($mutexName, 'nonblocking');
        $timestamp = time();

        if (! file_exists($mutexPath)) {
            $mutexFile = $this->createMutexFile($mutexPath);
        } else {
            // If file exists, it may mean that another process owns the mutex.
            // Need to compare file's data to see if lock is still valid.
            $data = $this->nonBlockingGetData($mutexPath);
            $lastTouch = Arr::mustGet(self::NONBLOCKING_LIVE_TIMESTAMP_KEY, $data);
            
            if ($timestamp - $lastTouch <= $duration) {
                // Existing lock is still valid. Don't provide lock to
                // requesting process.
                return false;
            }

            // Lock has expired. Re-create mutex file.
            @unlink($mutexPath);
            $mutexFile = $this->createMutexFile($mutexPath);
        }

        $mutexId = md5($mutexName . time());

        $this->nonBlockingSetData($mutexPath, array(
            self::NONBLOCKING_LIVE_TIMESTAMP_KEY    => $timestamp,
            self::NONBLOCKING_LOCK_ID_KEY           => $mutexId
        ));

        $this->nonBlockingMutexes[$mutexName] = array(
            $mutexFile,
            $mutexPath,
            $mutexId
        );
        return true;
    }

    /**
     * nonBlockingUnlock release the lock been put by nonblockingLock
     *
     * @param string $mutexName the name of mutex
     * @return void
     */
    public function nonBlockingUnlock($mutexName)
    {
        if (! isset($this->nonBlockingMutexes[$mutexName])) {
            throw new \Exception("Non-blocking mutex not found for '{$mutexName}'");
        }

        list(
            $mutexFile,
            $mutexPath,
            $mutexId    ) = $this->nonBlockingMutexes[$mutexName];

        fclose($mutexFile);
        @unlink($mutexPath);
        unset($this->nonBlockingMutexes[$mutexName]);
    }

    /**
     * nonBlockingKeepLive update nonblocking-lock timestamp to current
     * so other process that wants to regain the lock will have
     * more up-to-date timestamp to tell if first lock is still live
     * 
     * @param string $mutexName the name of mutex
     * @return void 
     */
    public function nonBlockingKeepLive($mutexName)
    {
        if (! isset($this->nonBlockingMutexes[$mutexName])) {
            throw new \Exception("Non-blocking mutex not found for '{$mutexName}'");
        }

        list(
            $mutexFile,
            $mutexPath,
            $mutexId    ) = $this->nonBlockingMutexes[$mutexName];

        $data = $this->nonBlockingGetData($mutexPath);

        if (Arr::mustGet(self::NONBLOCKING_LOCK_ID_KEY, $data) != $mutexId) {
            // If the lock id in mutex file not equal to the one in memory
            // it means the process gets stuck too long and another process
            // has gained the lock. Current process should halt execution.
            return false;
        }

        $this->nonBlockingSetData($mutexPath, array(
            self::NONBLOCKING_LOCK_ID_KEY        => $mutexId,
            self::NONBLOCKING_LIVE_TIMESTAMP_KEY => time()
        ));
        return true;
    }

    /**
     * Composes path to mutex file.
     *
     * @param string $mutexName the name of mutex
     * @param string $mutexType
     * 
     * @return string
     */
    private function mutexPath($mutexName, $mutexType)
    {
        return "{$this->mutexDir}/{$mutexName}_{$mutexType}";
    }

    /**
     * Creates mutex file.
     *
     * @param string $mutexPath
     *
     * @return Resource
     * @throws Exception If mutex file could not be created.
     */
    private function createMutexFile($mutexPath)
    {
        if (! file_exists($this->mutexDir)) {
            if (! mkdir($this->mutexDir, 0777, true)) {
                throw new \Exception("Could not create mutex dir at {$this->mutexDir}");
            }
        }

        $mutexFile = fopen($mutexPath, 'w+');
        if (! $mutexFile) {
            throw new \Exception("Could not create mutex file at {$mutexPath}");
        }
        return $mutexFile;
    }

    /**
     * save data into non-blocking mutex
     *
     * @param string $mutexPath
     * @param array $data any data with key value pair
     *
     * @return void
     * @throws Exception If data couldn't be written to mutex file.
     */
    private function nonBlockingSetData($mutexPath, array $data)
    {
        if (file_put_contents($mutexPath, serialize($data)) === false) {
            throw new \Exception("Could not set non-blocking data in {$mutexPath}");
        }
    }

    /**
     * get data from non-blocking mutex
     *
     * @param string $mutexPath
     *
     * @return array any data with key value pair
     * @throws Exception If mutex file couldn't be read or contains invalid data.
     */
    private function nonBlockingGetData($mutexPath)
    {
        $contents = @file_get_contents($mutexPath);
        if (! $contents) {
            throw new \Exception("Could not read non-blocking data in {$mutexPath}");
        }
        $data = @unserialize($contents);
        if ($data === false) {
            throw new \Exception("Invalid non-blocking data in {$mutexPath}");
        }
        return $data;
    }

    /**
     * Relenquishes all mutexes created by this process.
     *
     * @return void
     */
    public function unlockAll()
    {
        foreach (array_keys($this->blockingMutexes) as $mutexName) {
            $this->blockingUnlock($mutexName);
        }
        foreach (array_keys($this->nonBlockingMutexes) as $mutexName) {
            $this->nonBlockingUnlock($mutexName);
        }
    }

}
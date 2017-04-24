<?php
namespace CTLib\Component\ProcessLock;

use CTLib\Component\Monolog\Logger;

/**
 * Handles "external" management of process locks.
 * Here, "external" refers to a process/command outside of the processes that
 * actually own the locks.
 *
 * @author Mike Turoff
 */
class ProcessLockManager
{

    /**
     * Regular expression for matching paramters in a Lock ID string.
     * Example: this_is_a_lock_with_{oneParam}
     */
    const LOCK_ID_PARAM_PATTERN = '/{[A-Za-z0-9_-]+}/';


    /**
     * @var ProcessLock
     */
    protected $processLock;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     * Set of registered ProcessLockConsumerInterface services.
     */
    protected $consumers;


    /**
     * @param ProcessLock $processLock
     * @param Logger $logger
     */
    public function __construct(ProcessLock $processLock, Logger $logger)
    {
        $this->processLock  = $processLock;
        $this->logger       = $logger;
        $this->consumers    = [];
    }

    /**
     * Registers ProcessLockConsumerInterface service/instance.
     *
     * @param string $consumerId
     * @param ProcessLockConsumerInterface $consumer
     * @return void
     */
    public function registerConsumer(
        $consumerId,
        ProcessLockConsumerInterface $consumer
    ) {
        $this->consumers[$consumerId] = $consumer;
    }

    /**
     * Returns registered consumers.
     * @return array
     */
    public function getConsumers()
    {
        return $this->consumers;
    }

    /**
     * Returns specified consumer.
     * @return ProcessLockConsumerInterface
     * @throws RuntimeException
     */
    public function getConsumer($consumerId)
    {
        if (isset($this->consumers[$consumerId]) == false) {
            throw new \RuntimeException("No consumer registered for id '{$consumerId}'");
        }

        return $this->consumers[$consumerId];
    }

    /**
     * Returns parameters required to build process lock id for consumer.
     * @param string $consumerId
     * @return array
     * @throws RuntimeException
     */
    public function getConsumerLockIdParams($consumerId)
    {
        $consumer = $this->getConsumer($consumerId);
        $lockIdPattern = $consumer->getLockIdPattern();

        preg_match_all(self::LOCK_ID_PARAM_PATTERN, $lockIdPattern, $matches);

        if (empty($matches[0])) {
            return [];
        }

        $params = $matches[0];
        $params = array_map(function($p) { return trim($p, '{}'); }, $params);
        $params = array_unique($params);

        return $params;
    }

    /**
     * Finds existing process locks for consumer.
     * @param string $consumerId
     * @return array
     * @throws RuntimeException
     */
    public function findLocksForConsumer($consumerId)
    {
        $params = $this->getConsumerLockIdParams($consumerId);

        if (empty($params)) {
            // Lock ID is static string. Cheaper to using single lock finder
            // because it doesn't scan through Redis keys.
            return $this->findLockForConsumer($consumerId);
        }

        $wildcardId = $this->getWildcardLockIdForConsumer($consumerId);
        return $this->processLock->inspectLocksByPattern($wildcardId);
    }

    /**
     * Finds existing lock for consumer using passed lock id parameters.
     * @param string $consumerID
     * @param array $lockIdParams
     * @return array
     * @throws RuntimeException
     */
    public function findLockForConsumer($consumerId, array $lockIdParams = [])
    {
        $lockId = $this->getLockIdForConsumer($consumerId, $lockIdParams);
        return $this->processLock->inspectLock($lockId);
    }

    /**
     * Removes existing lock for consumer using passed lock id parameters.
     * @param string $consumerId
     * @param array $lockIdParams
     * @return boolean
     * @throws RuntimeException
     */
    public function removeLockForConsumer($consumerId, array $lockIdParams = [])
    {
        $lockId = $this->getLockIdForConsumer($consumerId, $lockIdParams);
        return $this->processLock->forceRemoveLock($lockId);
    }

    /**
     * Returns lock id for consumer using passed params.
     * @param string $consumerId
     * @param array $lockIdParams
     * @return string
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function getLockIdForConsumer($consumerId, array $lockIdParams = [])
    {
        $requiredParams = $this->getConsumerLockIdParams($consumerId);
        $missingParams = array_diff_key(
            array_flip($requiredParams),
            $lockIdParams
        );

        if ($missingParams) {
            throw new \InvalidArgumentException('Missing lock id params: ' . join(', ', $missingParams));
        }

        $consumer = $this->getConsumer($consumerId);
        $lockId = $consumer->getLockIdPattern();

        foreach ($lockIdParams as $param => $value) {
            $param = '{' . $param . '}';
            $lockId = str_replace($param, $value, $lockId);
        }

        return $lockId;
    }

    /**
     * Returns lock id for consumer using '*' in place of all parameters.
     * @param string $consumerId
     * @return string
     * @throws RuntimeException
     */
    protected function getWildcardLockIdForConsumer($consumerId)
    {
        $consumer = $this->getConsumer($consumerId);
        $lockIdPattern = $consumer->getLockIdPattern();
        return preg_replace(self::LOCK_ID_PARAM_PATTERN, '*', $lockIdPattern);
    }

}

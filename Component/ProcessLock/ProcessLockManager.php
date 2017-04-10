<?php
namespace CTLib\Component\ProcessLock;

use CTLib\Component\Monolog\Logger;


class ProcessLockManager
{

    const LOCK_ID_PARAM_PATTERN = '/{[A-Za-z0-9_-]+}/';


    public function __construct(ProcessLock $processLock, Logger $logger)
    {
        $this->processLock = $processLock;
        $this->logger = $logger;
        $this->consumers = [];
    }

    public function addConsumer($consumerId, ProcessLockConsumer $consumer)
    {
        $this->consumers[$consumerId] = $consumer;
    }

    public function getConsumers()
    {
        return $this->consumers;
    }

    public function getConsumer($consumerId)
    {
        if (isset($this->consumers[$consumerId]) == false) {
            throw new \RuntimeException("No consumer registered for id '{$consumerId}'");
        }

        return $this->consumers[$consumerId];
    }

    public function getConsumerLockIdParams($consumerId)
    {
        $consumer = $this->getConsumer($consumerId);
        $lockIdPattern = $consumer->getLockIdPattern();

        preg_match_all(self::LOCK_ID_PARAM_PATTERN, $lockIdPattern, $matches);

        if (isset($matches[1]) == false) {
            return [];
        }

        $params = $matches[1];
        $params = array_map(function($p) { return trim($p, '{}'); }, $params);
        $params = array_unique($params);

        return $params;
    }

    public function findLocksForConsumer($consumerId)
    {
        $consumer = $this->getConsumer($consumerId);
        $lockIdPattern = $consumer->getLockIdPattern();

        if (strpos($lockIdPattern, '{') === false) {
            return $this->findLockForConsumer($consumerId);
        }

        $wildcardIdPattern = $this->getWildcardLockIdPattern($lockIdPattern);
        return $this->processLock->inspectLocksByPattern($wildcardIdPattern);
    }

    public function findLockForConsumer($consumerId, array $lockIdParams = [])
    {
        $lockId = $this->getLockIdForConsumer($consumerId, $lockIdParams);
        return $this->processLock->inspectLock($lockId);
    }

    public function releaseLockForConsumer($consumerId, array $lockIdParams = [])
    {
        $lockId = $this->getLockIdForConsumer($consumerId, $lockIdParams);
        return $this->processLock->forceReleaseLock($lockId);
    }

    protected function getWildcardLockIdPattern($lockIdPattern)
    {
        return preg_replace(self::LOCK_ID_PARAM_PATTERN, '*', $lockIdPattern);
    }

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

        $lockId = $consumer->getLockIdPattern();

        foreach ($lockIdParams as $param => $value) {
            $param = '{' . $param . '}';
            $lockId = str_replace($param, $value, $lockId);
        }

        return $lockId;
    }


}

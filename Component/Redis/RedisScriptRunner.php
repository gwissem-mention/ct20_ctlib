<?php
namespace CTLib\Component\Redis;

/**
 * Assists in the running of Redis Lua scripts.
 *
 * @author Mike Turoff
 */
class RedisScriptRunner
{
    /**
     * The Redis client that will run the scripts.
     * @var Redis
     */
    protected $redis;

    /**
     * @param Redis $redis
     */
    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    /**
     * Runs the Lua script.
     *
     * @param string $script
     * @param array $keys       Enumerated array of Redis keys referenced by the
     *                          script.
     * @param array $args       Enumerated array of additional arguments
     *                          referenced by the script.
     * @return mixed
     * @throws RedisScriptException
     */
    public function runScript($script, array $keys = [], array $args = [])
    {
        $scriptSha = $this->getScriptSha($script);
        $numKeys = count($keys);
        $allArgs = array_merge($keys, $args);

        $this->redis->clearLastError();

        $result = $this->redis->evalSha($scriptSha, $allArgs, $numKeys);

        if (!$result && $this->triggeredMissingScriptError()) {
            // Script hasn't been loaded, yet. Need to run using eval.
            // This will also load the script for future calls to evalSha.

            // Clear last error so it can be accurately determined whether
            // this second attempt also triggered an error. If it did, then
            // there's an issue with the script.
            $this->redis->clearLastError();

            $result = $this->redis->eval($script, $allArgs, $numKeys);

            if (!$result && ($error = $this->redis->getLastError())) {
                $message = "Error '{$error}' loading Redis script '{$script}'";
                throw new RedisScriptException($message);
            }
        }

        return $result;
    }

    /**
     * Sets $redis.
     * @param Redis $redis
     * @return void
     */
    public function setRedis($redis)
    {
        $this->redis = $redis;
    }

    /**
     * Returns $redis.
     * @return Redis
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * Returns script's SHA.
     * @param string $script
     * @return string
     */
    protected function getScriptSha($script)
    {
        return sha1($script);
    }

    /**
     * Indicates whether a missing script error was just triggered.
     * @return boolean
     */
    protected function triggeredMissingScriptError()
    {
        $error = $this->redis->getLastError();
        return $error && strpos($error, 'NOSCRIPT') !== false;
    }
}

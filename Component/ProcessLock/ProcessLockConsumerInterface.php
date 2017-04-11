<?php
namespace CTLib\Component\ProcessLock;

/**
 * Defines classes that make use of ProcessLock.
 * @author Mike Turoff
 */
interface ProcessLockConsumerInterface
{

    /**
     * Returns human-readable lock name for referencing consumer.
     * @return string
     */
    public function getLockName();

    /**
     * Returns pattern for formatting lock id.
     * Examples:
     *      - this_is_a_lock_id
     *      - this_is_a_lock_id_with_a_{param}
     *      - this_is_a_lock_id_with_{param-1}_and_{param-2}
     * @return string
     */
    public function getLockIdPattern();

    /**
     * Returns number of seconds before lock expires on its own.
     * @return integer
     */
    public function getLockTtl();

}

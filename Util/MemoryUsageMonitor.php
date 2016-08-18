<?php
namespace CTLib\Util;

/**
 * Provides means for checking whether process has exceeded its maximum allowed
 * memory usage.
 *
 * @author Mike Turoff
 */
class MemoryUsageMonitor
{
    /**
     * The percentage of the PHP ini memory limit this process is actually
     * allowed to use.
     *
     * @var float
     */
    protected $memoryUsagePercentage;

    /**
     * The calculated memory limit (in bytes) for this process based on the PHP
     * 'memory_limit' ini and $memoryUsagePercentage.
     *
     * @var integer
     */
    protected $memoryLimit;


    /**
     * @param float $memoryUsagePercentage The percentage of the PHP ini memory
     *                                      limit this process is actually allowed
     *                                      to use.
     */
    public function __construct($memoryUsagePercentage)
    {
        if (!is_float($memoryUsagePercentage) || $memoryUsagePercentage <= 0) {
            throw new \InvalidArgumentException('$memoryUsagePercentage must be float greater than 0.0');
        }

        $this->memoryUsagePercentage = $memoryUsagePercentage;
    }

    /**
     * Returns calculated memory limit allowed for this process in bytes.
     *
     * @return integer  Returns -1 if there's no memory limit.
     */
    public function getMemoryLimit()
    {
        if (!$this->memoryLimit) {
            $this->memoryLimit = $this->calculateMemoryLimit();
        }

        return $this->memoryLimit;
    }

    /**
     * Indicates whether this process has exceeded its memory limit.
     *
     * @return boolean
     */
    public function hasExceededMemoryLimit()
    {
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryLimit == -1) {
            // No memory limit imposed on this process.
            return false;
        }

        $memoryUsage = memory_get_usage();

        if ($memoryUsage >= $memoryLimit) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Calculates the allowed memory limit.
     *
     * @return integer  Returns -1 if there is no limit.
     */
    protected function calculateMemoryLimit()
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit == -1) {
            // There is no memory limit.
            return $memoryLimit;
        }

        // PHP ini memory limit is stored as string (i.e, "256M").
        $memoryLimit = Util::iniStrToBytes($memoryLimit);

        // Reduce limit by allowed usage percentage.
        $memoryLimit *= $this->memoryUsagePercentage;
        $memoryLimit = floor($memoryLimit);

        return $memoryLimit;
    }

}

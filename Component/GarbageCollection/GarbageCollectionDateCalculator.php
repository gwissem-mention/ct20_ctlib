<?php
namespace CTLib\Component\GarbageCollection;

/**
 * Simple utility to calculate garbage collection date based on TTL days.
 * @author Mike Turoff
 */
class GarbageCollectionDateCalculator
{

    /**
     * Calculates garbage collection date.
     * @param integer $ttlDays
     * @return string YYYY-MM-DD
     * @throws InvalidArgumentException If $ttlDays is not integer >= 0
     */
    public function getGarbageCollectionDate($ttlDays)
    {
        if (is_int($ttlDays) == false && (int) $ttlDays == 0) {
            throw new \InvalidArgumentException("\$ttlDays '{$ttlDays}' must be integer value");
        }

        if ($ttlDays < 0) {
            throw new \InvalidArgumentException("\$ttlDays '{$ttlDays}' must be greater than or equal to 0");
        }

        $gcDate = new \DateTime;
        $gcDate->sub(new \DateInterval("P{$ttlDays}D"));
        return $gcDate->format('Y-m-d');
    }

    /**
     * Returns garbage collection timestamp.
     * @param integer $ttlDays
     * @return integer
     * @throws InvalidArgumentException If $ttlDays is not integer >= 0
     */
    public function getGarbageCollectionTime($ttlDays)
    {
        $gcDate = $this->getGarbageCollectionDate($ttlDays);
        return strtotime($gcDate);
    }

}

<?php
namespace CTLib\Util;

/**
 * Helper utility methods for date time.
 */
class CTDateTime extends \DateTime
{

    /**
     * Indicates whether this instance and $otherDateTime refer to the same
     * calendar day.
     *
     * @param DateTime $otherDateTime
     * @return boolean
     */
    public function isSameCalendarDay($otherDateTime)
    {
        // NOTE: Do not use CTDateTime::toISODate instead of DateTime::format
        // because $otherDateTime may not be a CTDateTime instance.
        return $this->format('Y-m-d') == $otherDateTime->format('Y-m-d');
    }

    /**
     * Returns DateTime formatted as ISO date string (YYYY-MM-DD).
     *
     * @return string
     */
    public function toISODate()
    {
        return $this->format('Y-m-d');
    }

    /**
     * Returns DateTime as UNIX timestamp.
     *
     * @return integer
     */
    public function toTimestamp()
    {
        return (int) $this->format('U');
    }

    /**
     * Get timestamp range given start/end time or start/interval
     *
     * @param string $start A date/time string. detail see: http://www.php.net/manual/en/datetime.formats.php
     * @param string $intervalOrEnd Interval string or end time
     * @param string $timezone Timezone string
     *
     * @return array An array of start and end timestamp
     */
    public static function getRange($start, $intervalOrEnd, $timezone)
    {
        $isIntervalMatched = preg_match("/^P[\dYMDWHMST]+/", $intervalOrEnd);

        if ($isIntervalMatched) {
            return self::getRangeWithInterval($start, $intervalOrEnd, $timezone);
        }
        return self::getRangeWithEndTime($start, $intervalOrEnd, $timezone);
    }

    /**
     * Get timestamp range given start time and finish time
     *
     * @param string $start A date/time string. detail see: http://www.php.net/manual/en/datetime.formats.php
     * @param string $end A date/time string. detail see: http://www.php.net/manual/en/datetime.formats.php
     * @param string $timezone Timezone string
     *
     * @exception When both start and end time are zero or null
     * @return array An array of start and end timestamp
     */
    public static function getRangeWithEndTime($start, $end, $timezone)
    {
        $startTime = 0;
        if (!empty($start)) {
            $startTime = new \DateTime($start, new \DateTimeZone($timezone));
        }
        $endTime = 0;
        if (!empty($end)) {
            $endTime = new \DateTime($end, new \DateTimeZone($timezone));
        }

        if ($startTime === 0 && $endTime === 0) {
            throw new \Exception("Can not get range, because start time and end time are all zero");
        }

        return array(
            $startTime === 0 ? 0 : $startTime->getTimestamp(),
            $endTime   === 0 ? 0 : $endTime->getTimestamp(),
        );
    }

    /**
     * Get timestamp range given start time and interval
     *
     * @param string $start A date/time string. detail see: http://www.php.net/manual/en/datetime.formats.php
     * @param string $interval Interval specification. detail see: http://www.php.net/manual/en/dateinterval.construct.php
     * @param string $timezone Timezone string
     *
     * @exception when $start time is 0 or null.
     * @return array An array of start and end timestamp
     */
    public static function getRangeWithInterval($start, $interval, $timezone)
    {
        if (is_null($start) || $start === 0) {
            throw new \Exception("Can not get range, because start time is invalid");
        }

        $startTime = new \DateTime($start, new \DateTimeZone($timezone));
        $endTime = clone $startTime;
        $endTime->add(new \DateInterval($interval));

        return array(
            $startTime->getTimestamp(),
            $endTime->getTimestamp(),
        );
    }

    /**
     * Get current unix Timestamp
     *
     * @return int current Unix Timestamp
     */
    public static function getCurrentTimestamp()
    {
        return gmdate("U");
    }

    /**
     * Get the timestamps for n days previous from midnight last
     *
     * @param integer $days
     * @param string  $timezone
     *
     * @note We may want to expand this into a proper time class.
     * @return array
     */
    public static function getTimeFrame($days, $timezone=null)
    {
        $time = array();

        $stoptime = new \DateTime(
            'midnight - 1 second',
            new \DateTimeZone($timezone)
        );

        // Use $days - 1 for interval so that range is 30 days inclusive.
        // Otherwise will be 31 days inclusive.
        $interval = 'P' . ($days - 1) . 'D';

        $starttime = clone $stoptime;
        $starttime = $starttime->sub(new \DateInterval($interval));
        // Set time back to midnight so we include entirety of range start.
        $starttime->setTime(0, 0, 0);
        
        $time['stop'] = $stoptime->format('U');
        $time['start'] = $starttime->format('U');
        $time['days'] = $days;
        return $time;
    }

    /**
     * Creates instance from UNIX timestamp and optional timezone.
     *
     * @param integer $timestamp
     * @param string $timezone
     *
     * @return CTDateTime
     */
    public static function fromTimestamp($timestamp, $timezone=null)
    {
        $dt = new self;
        $dt->setTimestamp($timestamp);
        if ($timezone) {
            $dt->setTimezone(new \DateTimezone($timezone));
        }
        return $dt;
    }

    /**
     * Calculates number of minutes different between two DateTime instances.
     *
     * NOTE: Substracts $datetime1 from $datetime2.
     *
     * @param DateTime $datetime1
     * @param DateTime $datetime2
     *
     * @return integer
     */
    public static function diffMinutes($datetime1, $datetime2)
    {
        $secsDiff = (int)$datetime2->format('U') - (int)$datetime1->format('U');
        return Util::secsToMins($secsDiff);
    }

}

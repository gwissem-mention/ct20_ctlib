<?php

namespace CTLib\Component\DataAccess\Filter;

use CTLib\Util\Arr;

/**
 * DataProviderFilter that filters based on date range.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class DateRangeFilter implements DataAccessFilterInterface
{
    const TODAY                     = 'today';
    const YESTERDAY                 = 'yesterday';
    const THIS_WEEK                 = 'thisWeek';
    const EARLIER_THAN_THIS_WEEK    = 'prevThisWeek';
    const SPECIFY                   = 'specify';

    const TYPE_DATETIME  = 'DATETIME';
    const TYPE_TIMESTAMP = 'TIMESTAMP';

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $timezone;

    /**
     * @var string
     */
    protected $fieldType;

    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * @var boolean
     */
    protected $includeWeekIds;

    /**
     * @param string $fieldName
     * @param string $timezone
     * @param string $dateFormat
     * @param string $fieldType
     * @param boolean $includeWeekIds
     */
    public function __construct(
        $fieldName,
        $timezone,
        $dateFormat,
        $fieldType=DateRangeFilter::TYPE_DATETIME,
        $includeWeekIds=false
    ) {
        $this->dateField    = $fieldName;
        $this->timezone     = $timezone;
        $this->fieldType    = $fieldType;
        $this->dateFormat   = $dateFormat;
        $this->includeWeekIds    = $includeWeekIds;
    }

    /**
     * @inherit
     */
    public function apply($dac, $value)
    {
        $date = Arr::mustGet("date", $value);
        $startTime = null;
        $stopTime = null;

        switch ($date["value"]) {
            case self::TODAY:
                $today = new \DateTime('today', $this->timezone);
                $startTime = $this->formatStartTime($today);
                $stopTime = $this->formatStopTime($today);

                break;

            case self::YESTERDAY:
                $yesterday = new \DateTime('yesterday', $this->timezone);
                $startTime = $this->formatStartTime($yesterday);
                $stopTime = $this->formatStopTime($yesterday);

                break;

            case self::THIS_WEEK:
                $weekEnd = new \DateTime('next Saturday', $this->timezone);
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $startTime = $this->formatStartTime($weekStart);  //end of last week
                $stopTime = $this->formatStopTime($weekEnd); // end of this week

                break;

            case self::EARLIER_THAN_THIS_WEEK:
                $stopTime = new \DateTime('Sunday last week', $this->timezone);
                // NO list of week ids needed

                break;

            case self::SPECIFY:
                list(
                    $dateFromDateTime, $dateToDateTime) = $this->formatDateFromTo(
                    Arr::findByKeyChain($value, "dateFrom.value"),
                    Arr::findByKeyChain($value, "dateTo.value")
                );
                $startTime = $this->formatStartTime($dateFromDateTime);
                $stopTime = $this->formatStopTime($dateToDateTime);

                break;

            default:
                throw new \Exception("date can not be found");
        }

        if ($startTime) {
            $dac->addFilter(
                $this->dateField,
                $startTime,
                'gte'
            );
        }
        if ($stopTime) {
            $dac->addFilter(
                $this->dateField,
                $stopTime,
                'lte'
            );
        }

        if ($this->includeWeekIds
            && ($startTime && $stopTime)) {
            $weekIdList = null;
            if ($date["value"] == self::THIS_WEEK) {
                $this->getWeekIds($stopTime, $stopTime);
            } else {
                $this->getWeekIds($startTime, $stopTime);
            }
            if ($weekIdList) {
                $dac->addFilter(
                    'startDateWeek',
                    $weekIdList,
                    'in'
                );
            }
        }
    }

    /**
     * Format DateFromTo
     *
     * @param mixed $dateFrom This is a description
     * @param mixed $dateTo This is a description
     *
     * @return mixed This is the return value description
     *
     */
    protected function formatDateFromTo($dateFrom, $dateTo)
    {
        if (empty($dateFrom) || empty($dateTo)) {
            return null;
        }

        $dateFromDateTime = \DateTime::createFromFormat(
            $this->dateFormat,
            $dateFrom,
            $this->timezone
        );
        $dateFromDateTime->setTime(0, 0);

        $dateToDateTime = \DateTime::createFromFormat(
            $this->dateFormat,
            $dateTo,
            $this->timezone
        );
        $dateToDateTime->setTime(0, 0);

        return [
            $dateFromDateTime,
            $dateToDateTime
        ];
    }

    /**
     * Format DateTime By Type
     *
     * @param \DateTime $datetime This is a description
     * @param string $type This is a description
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function formatStartTime(\DateTime $datetime)
    {
        if ($this->fieldType == static::TYPE_DATETIME) {
            return $datetime->format("Y-m-d 00:00:00");
        }

        if ($this->fieldType == static::TYPE_TIMESTAMP) {
            return (int) $datetime->format("U");
        }

        throw new \Exception("type can not be found");
    }

    /**
     * Format DateTime By Type
     *
     * @param \DateTime $datetime This is a description
     * @param string $type This is a description
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function formatStopTime(\DateTime $datetime)
    {
        if ($this->fieldType == static::TYPE_DATETIME) {
            return $datetime->format("Y-m-d 23:59:59");
        }

        if ($this->fieldType == static::TYPE_TIMESTAMP) {
            return $datetime->format("U") + 24 * 3600 - 1;
        }

        throw new \Exception("type can not be found");
    }

    /**
     * based on starttime to stoptime, get a list of ISO Week Ids
     *
     * @param \DateTime $startDateTime
     * @param \DateTime $endDateTime
     *
     * @return array | empty array
     *
     * @throws \Exception
     */
    protected function getWeekIds($startDateTime, $endDateTime)
    {
        if ($this->fieldType == static::TYPE_DATETIME) {
            // convert string to int date value
            $startDateTime = strtotime($startDateTime);
            $endDateTime = strtotime($endDateTime);
        }

        //get year of date for 'same vs. future' check
        $sYear = date('Y', $startDateTime);
        $eYear = date('Y', $endDateTime);

        //get ISO Week Id
        $startWeekId = idate('W', $startDateTime);
        $endWeekId = idate('W', $endDateTime);

        $weekIds = [];
        if ($sYear == $eYear) {
            // same year, get weeks from/including start to end
            $weekIds = range($startWeekId,$endWeekId);

        } else {
            // EndYear is greater than StartYear -> range spans into 'next' year
            // $Start goes to end of start year
            // $End backtracks to start of end year

            // the EndWeek is in the future (range spans into next year), roll back to start of year (week 1)
            $endWeekIds = range(1,$endWeekId);

            // $using startWeekId, loop to end of Start Year
            $endOfStartYear = idate('W', strtotime($sYear . '-12-31 23:59:59'));
            $startWeekIds = range($startWeekId,$endOfStartYear);

            $weekIds = array_merge($startWeekIds, $endWeekIds);

        }

        return $weekIds;
    }

}

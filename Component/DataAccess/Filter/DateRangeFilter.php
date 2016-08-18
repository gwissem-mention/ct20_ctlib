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

    const TYPE_DATE      = 'DATE';
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
     * @var string
     */
    protected $weekIdFieldName;

    /**
     * @param string $fieldName
     * @param string $timezone
     * @param string $dateFormat
     * @param string $fieldType
     * @param string|null $weekIdFieldName
     */
    public function __construct(
        $fieldName,
        $timezone,
        $dateFormat,
        $fieldType=DateRangeFilter::TYPE_DATETIME,
        $weekIdFieldName = null
    ) {
        $this->dateField       = $fieldName;
        $this->timezone        = $timezone;
        $this->fieldType       = $fieldType;
        $this->dateFormat      = $dateFormat;
        $this->weekIdFieldName = $weekIdFieldName;
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
                $startTime = new \DateTime('today', $this->timezone);
                $stopTime = $startTime;

                break;

            case self::YESTERDAY:
                $startTime = new \DateTime('yesterday', $this->timezone);
                $stopTime = $startTime;

                break;

            case self::THIS_WEEK:
                $startTime = new \DateTime('Sunday last week', $this->timezone);
                $stopTime = new \DateTime('next Saturday', $this->timezone);

                break;

            case self::EARLIER_THAN_THIS_WEEK:
                $stopTime = new \DateTime('Sunday last week', $this->timezone);

                break;

            case self::SPECIFY:
                list(
                    $dateFromDateTime, $dateToDateTime) = $this->formatDateFromTo(
                    Arr::findByKeyChain($value, "dateFrom.value"),
                    Arr::findByKeyChain($value, "dateTo.value")
                );
                $startTime = $dateFromDateTime;
                $stopTime = $dateToDateTime;

                break;

            default:
                throw new \Exception("date can not be found");
        }

        if (!$startTime) {
            $startTime = new \DateTime('01/01/2000', $this->timezone);
        }

        $dac->addFilter(
            $this->dateField,
            $this->formatStartTime($startTime),
            'gte'
        );

        $dac->addFilter(
            $this->dateField,
            $this->formatStopTime($stopTime),
            'lte'
        );

        if ($this->weekIdFieldName) {
            if ($date["value"] == self::EARLIER_THAN_THIS_WEEK) {
                // in finding an ISO StartWeekId issue with iDate()
                // - 01/01/2000 - will result in a weekId of 52 NOT 1
                // we will default $startWeekIds array to all possible startWeekIds,
                // 1 - 53 covers any possible max startWeekId including leapyears
                $startWeekIds = range(1, 53);
            } else {
                $startWeekIds = $this->getWeekIds($startTime, $stopTime);
            }
            $dac->addFilter(
                $this->weekIdFieldName,
                $startWeekIds,
                'in'
            );
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
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function formatStartTime(\DateTime $datetime)
    {
        if ($this->fieldType == static::TYPE_DATE) {
            return $datetime->format("Y-m-d");
        }

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
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function formatStopTime(\DateTime $datetime)
    {
        if ($this->fieldType == static::TYPE_DATE) {
            return $datetime->format("Y-m-d");
        }

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
     */
    protected function getWeekIds($startDateTime, $endDateTime)
    {
        //get the ISO week year of date for 'same vs. future' check
        $startYear = date('o', $startDateTime->getTimestamp());
        $endYear = date('o', $endDateTime->getTimestamp());

        //get ISO Week Id
        $startWeekId = date('W', $startDateTime->getTimestamp());
        $endWeekId = date('W', $endDateTime->getTimestamp());

        $weekIds = [];

        if ($startYear == $endYear) {
            // same year, get weeks from/including start to end
            $weekIds = range($startWeekId, $endWeekId);
        } else {
            // EndYear is greater than StartYear -> range spans into 'next' year
            // $Start goes to end of start year
            // $End backtracks to start of end year

            // the EndWeek is in the future (range spans into next year), roll back to start of year (week 1)
            $endWeekIds = range(1, $endWeekId);

            // $using startWeekId, loop to end of Start Year
            $endOfStartYear = date('W', strtotime($startYear . '-12-31 23:59:59'));
            $startWeekIds = range($startWeekId, $endOfStartYear);

            $weekIds = array_unique(array_merge($startWeekIds, $endWeekIds));
        }

        return $weekIds;
    }

}

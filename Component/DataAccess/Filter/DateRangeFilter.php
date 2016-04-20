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
     * @param string $fieldName
     * @param string $timezone
     * @param string $dateFormat
     * @param string $fieldType
     */
    public function __construct(
        $fieldName,
        $timezone,
        $dateFormat,
        $fieldType=DateRangeFilter::TYPE_DATETIME
    ) {
        $this->dateField    = $fieldName;
        $this->timezone     = $timezone;
        $this->fieldType    = $fieldType;
        $this->dateFormat   = $dateFormat;
    }

    /**
     * @inherit
     */
    public function apply($dac, $value)
    {
        $date = Arr::mustGet("date", $value);

        switch ($date["value"]) {
            case self::TODAY:
                $today = new \DateTime('today', $this->timezone);
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStartTime($today),
                    'gte'
                );
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStopTime($today),
                    'lte'
                );
                break;

            case self::YESTERDAY:
                $yesterday = new \DateTime('yesterday', $this->timezone);
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStartTime($yesterday),
                    'gte'
                );
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStopTime($yesterday),
                    'lte'
                );
                break;

            case self::THIS_WEEK:
                $weekEnd = new \DateTime('next Saturday', $this->timezone);
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStartTime($weekStart),
                    'gte'
                );
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStopTime($weekEnd),
                    'lte'
                );
                break;

            case self::EARLIER_THAN_THIS_WEEK:
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStartTime($weekStart),
                    'lte'
                );
                break;

            case self::SPECIFY:
                list(
                    $dateFromDateTime, $dateToDateTime) = $this->formatDateFromTo(
                        Arr::findByKeyChain($value, "dateFrom.value"),
                        Arr::findByKeyChain($value, "dateTo.value")
                    );

                // Use the passed range from and to dates.
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStartTime($dateFromDateTime),
                    'gte'
                );
                $dac->addFilter(
                    $this->dateField,
                    $this->formatStopTime($dateToDateTime),
                    'lte'
                );
                break;

            default:
                throw new \Exception("date can not be found");
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
}

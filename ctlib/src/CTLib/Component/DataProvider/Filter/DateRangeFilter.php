<?php
namespace CTLib\Component\DataProvider\Filter;

use CTLib\Component\DataProvider\DataProviderFilter,
    CTLib\Util\Arr;

/**
 * DataProviderFilter that filters based on date range.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class DateRangeFilter implements DataProviderFilter
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
    protected $dateField;

    /**
     * @var string
     */
    protected $timezone;

    /**
     * @var string
     */
    protected $fieldType;

    /**
     * @param string $dateField
     * @param string $timezone
     */
    public function __construct($dateField, $timezone, $filedType=DateRangeFilter::TYPE_DATETIME)
    {
        $this->dateField    = $dateField;
        $this->timezone     = $timezone;
        $this->fieldType    = $filedType;
    }

    /**
     * @inherit
     */
    public function apply($qbr, $value)
    {
        $date = Arr::mustGet("date", $value);
        switch ($date["value"])
        {
            case self::TODAY:
                $today = new \DateTime('today', $this->timezone);
                $qbr->andWhere("{$this->dateField} BETWEEN :todayStart AND :todayEnd")
                    ->setParameter('todayStart', $this->formatStartTime($today))
                    ->setParameter('todayEnd', $this->formatStopTime($today));
                break;

            case self::YESTERDAY:
                $yesterday = new \DateTime('yesterday', $this->timezone);
                $qbr->andWhere("{$this->dateField} BETWEEN :yesterdayStart AND :yesterdayEnd")
                    ->setParameter('yesterdayStart', $this->formatStartTime($yesterday))
                    ->setParameter('yesterdayEnd', $this->formatStopTime($yesterday));
                break;

            case self::THIS_WEEK:
                $today = new \DateTime('today', $this->timezone);
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $qbr->andWhere("{$this->dateField} BETWEEN :weekStart AND :today")
                    ->setParameter('weekStart', $this->formatStartTime($weekStart))
                    ->setParameter('today', $this->formatStopTime($today));
                break;

            case self::EARLIER_THAN_THIS_WEEK:
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $qbr->andWhere("{$this->dateField} < :weekStart")
                    ->setParameter('weekStart', $this->formatStartTime($weekStart));
                break;

            case self::SPECIFY:
                $dateFrom = Arr::get('dateFrom', $value);
                $dateTo   = Arr::get('dateTo', $value);
                if (empty($dateFrom) || empty($dateTo)) {
                    break;
                }
                $dateFromDateTime = new \DateTime($dateFrom["value"], $this->timezone);
                $dateToDateTime   = new \DateTime($dateTo["value"], $this->timezone);
                
                // Use the passed range from and to dates.
                $qbr->andWhere("{$this->dateField} BETWEEN :from AND :to")
                    ->setParameter('from', $this->formatStartTime($dateFromDateTime))
                    ->setParameter('to', $this->formatStopTime($dateToDateTime));
                break;

            default:
                throw new \Exception("date can not be found");
        }
    }

    /**
     * Format DateTime By Type
     *
     * @param DateTime $datetime This is a description
     * @param string $type This is a description
     * @return mixed 
     *
     */
    protected function formatStartTime(\DateTime $datetime)
    {
        if ($this->fieldType == static::TYPE_DATETIME) {
            return $datetime->format("Y-m-d 00:00:00");
        }
        
        if ($this->fieldType == static::TYPE_TIMESTAMP) {
            return $datetime->format("U");
        }
        
        throw new \Exception("type can not be found");
    }

    /**
     * Format DateTime By Type
     *
     * @param DateTime $datetime This is a description
     * @param string $type This is a description
     * @return mixed 
     *
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
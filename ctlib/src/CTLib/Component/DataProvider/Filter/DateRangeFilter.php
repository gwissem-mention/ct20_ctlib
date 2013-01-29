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
    
    /**
     * @var string
     */
    protected $dateField;

    /**
     * @var string
     */
    protected $timezone;


    /**
     * @param string $dateField
     * @param string $timezone
     */
    public function __construct($dateField, $timezone=null)
    {
        $this->dateField    = $dateField;
        $this->timezone     = $timezone;
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
                    ->setParameter('todayStart', $today->format('Y-m-d 00:00:00'))
                    ->setParameter('todayEnd', $today->format('Y-m-d 23:59:59'));
                break;

            case self::YESTERDAY:
                $yesterday = new \DateTime('yesterday', $this->timezone);
                $qbr->andWhere("{$this->dateField} BETWEEN :yesterdayStart AND :yesterdayEnd")
                    ->setParameter('yesterdayStart', $yesterday->format('Y-m-d 00:00:00'))
                    ->setParameter('yesterdayEnd', $yesterday->format('Y-m-d 23:59:59'));
                break;

            case self::THIS_WEEK:
                $today = new \DateTime('today', $this->timezone);
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $qbr->andWhere("{$this->dateField} BETWEEN :weekStart AND :today")
                    ->setParameter('weekStart', $weekStart->format('Y-m-d 00:00:00'))
                    ->setParameter('today', $today->format('Y-m-d 23:59:59'));
                break;

            case self::EARLIER_THAN_THIS_WEEK:
                $weekStart = new \DateTime('Sunday last week', $this->timezone);
                $qbr->andWhere("{$this->dateField} < :weekStart")
                    ->setParameter('weekStart', $weekStart->format('Y-m-d 00:00:00'));
                break;

            case self::SPECIFY:
                $dateFrom = Arr::mustGet('dateFrom', $value);
                $dateTo   = Arr::mustGet('dateTo', $value);
                $dateFromDateTime = new \DateTime($dateFrom["value"], $this->timezone);
                $dateToDateTime   = new \DateTime($dateTo["value"], $this->timezone);
                
                // Use the passed range from and to dates.
                $qbr->andWhere("{$this->dateField} BETWEEN :from AND :to")
                    ->setParameter('from', $dateFromDateTime->format("Y-m-d 00:00:00"))
                    ->setParameter('to', $dateToDateTime->format("Y-m-d 23:59:59"));
                break;

            default:
                throw new \Exception("date can not be found");
        }
    }

}
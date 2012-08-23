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
        switch ($value)
        {
            case self::TODAY:
                $today = new \DateTime('today', $this->timezone);
                $qbr->andWhere("{$this->dateField} = :today")
                    ->setParameter('today', $today->format('Y-m-d'));
                break;
            case self::YESTERDAY:
                $yesterday = new \DateTime('yesterday', $this->timezone);
                $qbr->andWhere("{$this->dateField} = :yesterday")
                    ->setParameter('yesterday', $yesterday->format('Y-m-d'));
                break;
            case self::THIS_WEEK:
                $today = new \DateTime('today', $this->timezone);
                $weekStart = clone($today);
                $weekStart->sub(new \DateInterval('P6D'));

                $qbr->andWhere("{$this->dateField} >= :weekStart")
                    ->andWhere("{$this->dateField} <= :today")
                    ->setParameter('weekStart', $weekStart->format('Y-m-d'))
                    ->setParameter('today', $today->format('Y-m-d'));
                break;
            case self::EARLIER_THAN_THIS_WEEK:
                $today = new \DateTime('today', $this->timezone);
                $weekStart = clone($today);
                $weekStart->sub(new \DateInterval('P6D'));

                $qbr->andWhere("{$this->dateField} < :weekStart")
                    ->setParameter('weekStart', $weekStart->format('Y-m-d'));
                break;
            default:
                // Use the passed range from and to dates.
                $qbr->andWhere("{$this->dateField} >= :from")
                    ->andWhere("{$this->dateField} <= :to")
                    ->setParameter('from', Arr::mustGet('dateFrom', $value))
                    ->setParameter('to', Arr::mustGet('dateTo', $value));
                break;
        }
    }



}
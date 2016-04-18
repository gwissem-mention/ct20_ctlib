<?php

namespace CTLib\Component\DataAccess;

use CTLib\Component\HttpFoundation\CsvResponse;

/**
 * Facilitates retrieving and processing nosql
 * results into csv output.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CsvDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];

    /**
     * @var $template
     */
    protected $template;

    /**
     * @var Templating Engine
     */
    protected $templating;


    /**
     * @param string         $template
     * @param Templating     $templating
     */
    public function __construct($template, $templating)
    {
        $this->template   = $template;
        $this->templating = $templating;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function start(array $fields)
    {
        $this->records = [];
    }

    /**
     * {@inheritdoc}
     *
     * Perform the necessary processing to create a flat
     * Record of field values.
     *
     * @param array $record   Document data retrieved from API
     */
    public function addRecord(array $record)
    {
        $this->records[] = $record;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function end()
    {
        return $this->templating->render($this->template, $this->records);
    }
}

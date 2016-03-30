<?php

namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class DataProvider2
{
    /**
     * @var DataAccessInterface
     */
    protected $input;

    /**
     * @var DataOutputInterface
     */
    protected $output;


    public function __construct(
        DataAccessInterface $input,
        DataOutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->transforms = [];
        $this->onRecordAdded = null;
    }

    public function addRecordTransform($transform)
    {
        $this->transforms[] = $transform;
    }

    public function getResult()
    {
        $data = $this->input->getData();

        $this->output->start($this->input);

        foreach ($data as $record) {
            $this->applyRecordTransforms($record);
            $this->output->addRecord($record);

            if ($this->onRecordAdded) {
                call_user_func($this->onRecordAdded($record, $this->output));
            }

        }

        return $this->output->end($this->input);
    }

    protected function applyRecordTransforms(&$record)
    {
        $context = [];

        foreach ($this->transforms as $transform) {
            call_user_func($transform, $record, $context, $this->output);
        }
    }

}

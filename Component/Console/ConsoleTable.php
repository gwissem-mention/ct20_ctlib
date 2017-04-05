<?php
namespace CTLib\Component\Console;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Displays tabular data in the console.
 * @author Mike Turoff
 */
class ConsoleTable
{

    /**
     * @var array
     */
    protected $columns;

    /**
     * @var array
     */
    protected $records;


    public function __construct()
    {
        $this->columns = [];
        $this->records = [];
    }

    /**
     * Adds column.
     * @param string $name
     * @param integer $width
     * @return ConsoleTable
     * @throws RuntimeException
     */
    public function addColumn($name, $width)
    {
        if ($this->records) {
            throw new \RuntimeException("Cannot add column after adding first record");
        }

        $column = new \stdClass;
        $column->name = $name;
        $column->width = $width;
        $this->columns[] = $column;
        return $this;
    }

    /**
     * Adds record.
     * @param string|array ...$values
     * @return ConsoleTable
     * @throws RuntimeException
     */
    public function addRecord(...$values)
    {
        if (empty($this->columns)) {
            throw new \RuntimeException("Cannot add record without adding columns first");
        }

        if (count($values) != count($this->columns)) {
            throw new \RuntimeException("Must pass 1 value for each column (null is ok)");
        }

        $this->records[] = $values;
        return $this;
    }

    /**
     * Outputs table to console.
     * @param OutputInterface $output
     * @param string $color
     * @return void
     */
    public function output(OutputInterface $output, $color = 'cyan')
    {
        $this->tableWidth = $this->getTableWidth();
        $this->outputHeader($output, $color);
        $this->outputRecords($output, $color);
    }

    /**
     * Outputs table header to console.
     * @param OutputInterface $output
     * @param string $color
     * @return void
     */
    protected function outputHeader(OutputInterface $output, $color)
    {
        $header = "<options=bold;fg=white;bg={$color}>";

        foreach ($this->columns as $column) {
            $paddedName = $this->abridgeAndPad($column->name, $column->width);
            $header .= $paddedName;
        }

        $header .= "</>";
        $output->writeln($header);
        $output->writeln("");
    }

    /**
     * Outputs table records to console.
     * @param OutputInterface $output
     * @param string $color
     * @return void
     */
    protected function outputRecords(OutputInterface $output, $color)
    {
        $recordCount = count($this->records);

        foreach ($this->records as $recordIndex => $record) {
            $formattedRecord = "";

            foreach ($record as $columnIndex => $value) {
                $column = $this->columns[$columnIndex];

                if (is_null($value)) {
                    $formattedValue = str_repeat(' ', $column->width);
                } else {
                    if (is_array($value)) {
                        list($value, $format) = $value;
                    } else {
                        $format = null;
                    }

                    $formattedValue = $this->abridgeAndPad($value, $column->width);

                    if ($format) {
                        $formattedValue = "<{$format}>{$formattedValue}</>";
                    }
                }

                $formattedRecord .= $formattedValue;
            }

            $output->writeln($formattedRecord);

            if ($recordIndex == $recordCount - 1
                || $this->hasSpansInRecord($this->records[$recordIndex + 1]) == false
            ) {
                $divider = "<fg={$color}>" . str_repeat('-', $this->tableWidth) . "</>";
                $output->writeln($divider);
            }
        }
    }

    /**
     * Calculates table width based on added columns.
     * @return integer
     */
    protected function getTableWidth()
    {
        return array_reduce(
            $this->columns,
            function($width, $col) { $width += $col->width; return $width; },
            0
        );
    }

    /**
     * Abridge and pad value to length.
     * @param string $str
     * @param integer $length
     * @return string
     */
    protected function abridgeAndPad($str, $length)
    {
        $str = substr($str, 0, $length - 1);
        $str = str_pad($str, $length);
        return $str;
    }

    /**
     * Indicates whether there are spans (nulls) in record.
     * @param array $record
     * @return boolean
     */
    protected function hasSpansInRecord(array $record)
    {
        foreach ($record as $value) {
            if (is_null($value)) {
                return true;
            }
        }

        return false;
    }

}

<?php
namespace CTLib\Component\Console;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Utility class that creates specialized console outputs.
 * @author Mike Turoff
 */
class ConsoleOutputHelper
{

    /**
     * @var OutputInterface
     */
    protected $output;


    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Outputs enumerated list of items.
     * @param array $items
     * @param integer $indent Number of spaces to indent before each item.
     * @return void
     */
    public function outputEnumeratedList(array $items, $indent = 3)
    {
        $itemCount = count($items);
        $indexPadLength = strlen($itemCount) +  2;

        foreach ($items as $i => $item) {
            $index = $i + 1;

            $display = str_repeat(" ", $indent)
                     . str_pad($index . '.', $indexPadLength)
                     . $item;

            $this->output->writeln($display);
        }
    }

    /**
     * Outputs ConsoleProcessResult.
     * @param ConsoleProcessResult $result
     * @param integer $padWidth
     * @return void
     */
    public function outputProcessResult(
        ConsoleProcessResult $result,
        $padWidth = 40
    ) {
        if ($result->isSuccess()) {
            $bgColor = 'green';
        } else {
            $bgColor = 'red';
        }

        $display = str_pad($result->getProcessName(), $padWidth, '.')
                 . "<fg=white;bg={$bgColor};options=bold>"
                 . ' ' . $result->getMessage() . ' '
                 . '</>';

        $this->output->writeln($display);
    }

    /**
     * Outputs list of command actions.
     * @param ...$actions Each $actions array passed will be output as a group
     *                    of actions.
     * @return void
     */
    public function outputActionList(...$actions)
    {
        $dividerLength = 120;

        $this->output->writeln("");
        $this->output->writeln("<bg=blue;fg=white;options=bold> Available Actions: </>");

        foreach ($actions as $groupActions) {
            $this->output->writeln("");
            $this->output->writeln(str_repeat('-', $dividerLength));

            foreach ($groupActions as $actionName => $actionDescription) {
                $action = $this->formatAction($actionName, $actionDescription);
                $this->output->writeln($action);
            }
        }

        $this->output->writeln("");
        $this->output->writeln("");
    }

    /**
     * Outputs single attribute...value pair.
     * @param string $attribute
     * @param string $value
     * @param integer $attributeWidth
     * @param string $attributePadChar
     * @return void
     */
    public function outputAttributeValuePair(
        $attribute,
        $value,
        $attributeWidth = 40,
        $attributePadChar = '.'
    ) {
        $attributeDisp = "<options=bold>"
                       . str_pad($attribute, $attributeWidth, $attributePadChar)
                       . "</>";

        $this->output->writeln($attributeDisp . $value);
    }

    /**
     * Formats command action.
     * @param string $name
     * @param string $descrption
     * @return string
     */
    protected function formatAction($name, $description)
    {
        $padLength = 35;

        return "<options=bold>" . str_pad($name, $padLength) . "</>"
                . $description;
    }

}

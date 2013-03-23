<?php
namespace CTLib\Component\Console;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    CTLib\Util\Util;


/**
 * Base class to introduce helper methods for commands.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
abstract class BaseCommand extends ContainerAwareCommand
{
    
    /**
     * Runs standard command configuration.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName($this->formulateName())
            ->addOption('debug', '', InputOption::VALUE_NONE);
    }
    
    /**
     * Automatically formulates command name based on class name.
     *
     * For example, command class of MyAwesomeCommand will have command name
     * of myawesome.
     *
     * @return string
     */
    protected function formulateName()
    {
        $classTokens    = explode('\\', get_class($this));
        $bundleName     = array_shift($classTokens);
        $className      = array_pop($classTokens);
        $prefix         = strtolower(str_replace('Bundle', '', $bundleName));
        $command        = strtolower(str_replace('Command', '', $className)); 

        return "{$prefix}:{$command}";
    }

    /**
     * Returns Container service identified by $serviceName.
     *
     * @param string $serviceName
     * @return mixed
     */
    protected function getService($serviceName)
    {
        return $this->getContainer()->get($serviceName);
    }

    /**
     * Shortcut to get Logger service.
     *
     * @return Logger
     */
    protected function logger()
    {
        return $this->getService('logger');
    }

    /**
     * Shortcut to access EntityManager identified by $name.
     *
     * @param string $name
     * @return EntityManager
     */
    protected function em($name='default')
    {
        return $this->getService("doctrine.orm.{$name}_entity_manager");
    }

    /**
     * Replaces entity manager in service container with a new one.
     *
     * @param EntityManager $em
     * @param string $name          Entity manager name. Uses 'default' if not
     *                              passed.
     *
     * @return void
     */
    protected function replaceEm($em, $name='default')
    {
        $this->getService('entity_manager_reopener')->reopen($em, $name);
    }

    /**
     * Shortcut to access entity repository from default entity manager.
     *
     * @param $entityName
     * @param $bundleName   If null, uses command's bundle.
     *
     * @return EntityRepository
     */
    protected function repo($entityName, $bundleName=null)
    {
        $bundleName = $bundleName ?: $this->currentBundle();
        $bundleName = Util::append($bundleName, 'Bundle');
        return $this->em()->getRepository("{$bundleName}:{$entityName}");
    }

    protected function currentBundle()
    {
        if (! isset($this->currentBundle)) {
            $classTokens = explode('\\', get_class($this));
            $this->currentBundle = array_shift($classTokens);
        }
        return $this->currentBundle;
    }

    /**
     * Shortcut to accessing Symfony's object for working with interactive
     * console input.
     *
     * @return Dialog
     */
    protected function dialog()
    {
        return $this->getHelperSet()->get('dialog');
    }

    /**
     * Prompts user for required response.
     *
     * Will repeat prompt until user enters valid response.
     *
     * @param OutputInterface $output
     * @param string $prompt Automatically prefixes with "Enter "
     * @param callable $validator See Dialog::askAndValidate for info.
     *
     * @return mixed    Returns user's response.
     */
    protected function prompt($output, $prompt, $validator=null)
    {
        $prompt = $this->formatPromptMessage($prompt);
        $response = null;

        if (! $validator) {
            $validator = function($r) {
                if (is_null($r)) { throw new \Exception("Response required"); }
                return $r;
            };
        }

        do {
            $response = $this->dialog()->askAndValidate(
                $output,
                $prompt,
                $validator
            );
        } while (is_null($response));

        return $response;
    }

    /**
     * Prompts user for required boolean (y/n) response.
     *
     * Will repeat prompt until user enters valid response.
     *
     * @param OutputInterface $output
     * @param string $prompt Automatically prefixes with "Enter "
     *
     * @return boolean    Returns user's response.
     */
    protected function promptForBoolean($output, $prompt)
    {
        return $this->prompt($output, "{$prompt} (y/n)", function($r) {
            $r = strtolower($r);
            if ($r != 'y' && $r != 'n') {
                throw new \Exception("Response must be y/n");
            }
            return $r == 'y' ? true : false;
        });
    }

    /**
     * Prompts user for required id (int > 0) response.
     *
     * Will repeat prompt until user enters valid response.
     *
     * @param OutputInterface $output
     * @param string $prompt Automatically prefixes with "Enter "
     *
     * @return int    Returns user's response.
     */
    protected function promptForId($output, $prompt)
    {
        return $this->prompt($output, $prompt, function($r) {
            $r = (int) $r;
            if ($r <= 0) {
                throw new \Exception("Must be int greater than 0");
            }
            return $r;
        });
    }

    /**
     * Prompts user for optional response.
     *
     * Allows user to skip prompt by pressing RETURN. If user enters response,
     * it must pass $validator if applicable.
     *
     * @param OutputInterface $output
     * @param string $prompt Automatically prefixes with "Enter "
     * @param callable $validator See Dialog::askAndValidate for info.
     * @param mixed $default Value returned if user skips prompt.
     *
     * @return mixed    Returns user's response or $default if skipped.
     */
    protected function promptOptional($output, $prompt, $validator=null,
        $default=null)
    {
        $prompt = $this->formatPromptMessage($prompt, true);
        
        if ($validator) {
            return $this->dialog()->askAndValidate(
                $output,
                $prompt,
                $validator,
                false,
                $default
            );
        } else {
            return $this->dialog()->ask($output, $prompt, $default);
        }
    }

    /**
     * Prompts user for response based on passed choice list.
     *
     * @param OutputInterface $output
     * @param string $prompt        Automatically prefixes with "Enter "
     * @param array $choices        array($value => $label)
     * @param string $instruction   Displays before outputting choice list.
     *                              If not passed, will automatically display
     *                              "Please Choose {$prompt}:".
     *
     * @return mixed Returns user's response.
     */
    protected function promptChoice($output, $prompt, $choices, $instruction='')
    {
        if (! $instruction) {
            $instruction = "Please choose {$prompt}";
        }
        $instruction = "\n\n<info>{$instruction}:\n\n";
        foreach ($choices as $value => $label) {
            $instruction .= "\n[{$value}] {$label}";
        }
        $instruction .= "</info>\n\n";
        $output->writeln($instruction);

        $validator = function($r) use ($choices) {
            if (! isset($choices[$r])) {
                 throw new \Exception('Invalid response');
            }
            return $r;
        };
        return $this->prompt($output, $prompt, $validator);
    }

    /**
     * Formats prompt message.
     *
     * @param string $prompt
     * @param boolean $optional Indicates whether prompt is optional.
     *
     * @return string
     */
    protected function formatPromptMessage($prompt, $optional=false)
    {
        if ($optional) {
            $prompt .= ' (optional)';
        }

        return "<info>"
                . "Enter " . str_pad($prompt, 40, '.')
                . "</info>";
    }

    /**
     * Prompts user with confirmation dialog.
     *
     * Will repeat prompt until user enters 'y' or 'n'.
     *
     * @param OutputInterface $output
     * @param string $question
     *
     * @return boolean
     */
    protected function confirm($output, $question)
    {
        return $this->dialog()->askAndValidate(
            $output,
            "\n\n<question>{$question} (y/n)</question> ",
            function($r) {
                $r = strtolower($r);
                if ($r != 'y' && $r != 'n') {
                    throw new \Exception("Response must be y/n");
                }
                return $r == 'y' ? true : false;
            }
        );
    }
    
}
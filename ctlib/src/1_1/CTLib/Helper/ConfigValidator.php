<?php
namespace CTLib\Helper;

use Symfony\Component\Yaml\Yaml;

/**
 * Helper to validate configuration values.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */    
class ConfigValidator
{

    protected $validationRules;


    /**
     * @param array $validationRules    array($configKey => $test, ...)
     */
    public function __construct(array $validationRules)
    {
        $this->validationRules = $validationRules;
    }

    /**
     * Indicates whether configuration value is valid.
     *
     * @param string $configKey
     * @param mixed $configValue
     *
     * @return boolean
     * @throws Exception    If invalid test defined for $configKey.
     */
    public function isValid($configKey, $configValue)
    {
        if (! isset($this->validationRules[$configKey])) { return false; }

        $test = $this->validationRules[$configKey];

        switch ($test) {
            case 'string':
            case 'str':
                return is_string($configValue);
            break;

            case 'integer':
            case 'int':
                return is_int($configValue);
            break;

            case '+integer':
            case '+int':
                return is_int($configValue) && $configValue >= 0;
            break;

            case '-integer':
            case '-int':
                return is_int($configValue) && $configValue < 0;
            break;

            case 'float':
                return is_float($configValue);
            break;

            case '+float':
                return is_float($configValue) && $configValue >= 0;
            break;

            case '-float':
                return is_float($configValue) && $configValue < 0;
            break;

            case 'boolean':
            case 'bool':
                return is_bool($configValue);
            break;

            case 'array':
                return is_array($configValue);
            break;

            default:
                if (is_array($test)) {
                    return in_array($configValue, $test, true);
                } elseif (strpos($test, '/^') === 0) {
                    // Regular expression test.
                    $result = preg_match($test, $configValue);

                    if ($result === false) {
                        throw new \Exception("Invalid regexp ({$test}) for key: {$configKey}");
                    }
                    return $result === 1;
                } else {
                    throw new \Exception("Invalid validation test for key: {$configKey}");
                }
            break;
        }
    }

    /**
     * Returns all configuration keys defined in validation rules.
     *
     * @return array
     */
    public function getConfigKeys()
    {
        return array_keys($this->validationRules);
    }

    /**
     * Creates validator by loading validation rules from config file.
     *
     * @param string $path
     * @return ConfigValidator
     */
    public static function createFromFile($path)
    {
        $contents = file_get_contents($path);
        return new self(Yaml::parse($contents));
    }

}
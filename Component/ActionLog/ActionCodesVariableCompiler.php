<?php
namespace CTLib\Component\ActionLog;

use CTLib\Component\Cache\VariableCompilerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Compiles action codes from source YAMLs.
 *
 * @author Mike Turoff
 */
class ActionCodesVariableCompiler implements VariableCompilerInterface
{

    /**
     * Compiles action code set from source YAML files.
     *
     * @param array $sourcePaths
     * @return array
     */
    public function compile(array $sourcePaths)
    {
        $allActionCodes = [];

        foreach ($sourcePaths as $sourcePath) {
            $contents = file_get_contents($sourcePath);
            $actionCodes = Yaml::parse($contents);

            foreach ($actionCodes as $namespace => $namespaceActionCodes) {
                if (strpos($namespace, '.') !== false) {
                    throw new \RuntimeException("Action code namespace '{$namespace}' cannot contain a '.'");
                }

                foreach ($namespaceActionCodes as $name => $actionCode) {
                    if (strpos($name, '.') !== false) {
                        throw new \RuntimeException("Action code name '{$name}' cannot contain a '.'");
                    }

                    $qualifiedName = "{$namespace}.{$name}";

                    if ($inUseBy = array_search($actionCode, $allActionCodes)) {
                        throw new \RuntimeException("Action code '{$actionCode}' assigned to '{$qualifiedName}' is already assigned to '{$inUseBy}'");
                    }

                    $allActionCodes[$qualifiedName] = $actionCode;
                }
            }
        }

        return $allActionCodes;
    }
}

<?php
namespace CTLib\Component\ActionLog;

use CTLib\Component\Cache\VariableCompilerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Compiles actions from source YAMLs.
 *
 * @author Mike Turoff
 */
class ActionsVariableCompiler implements VariableCompilerInterface
{

    /**
     * Compiles action set from source YAML files.
     *
     * @param array $sourcePaths
     * @return array
     */
    public function compile(array $sourcePaths)
    {
        $actions = [];

        foreach ($sourcePaths as $sourcePath) {
            $contents = file_get_contents($sourcePath);
            $groupedActions = Yaml::parse($contents);

            foreach ($groupedActions as $group => $unqualifiedActions) {
                if (strpos($group, '.') !== false) {
                    throw new \RuntimeException("Action code group '{$group}' cannot contain a '.'");
                }

                $qualifiedActions = array_map(
                    function($action) use ($group) { return "{$group}.{$action}"; },
                    $unqualifiedActions
                );

                $actions = array_merge($actions, $qualifiedActions);
            }
        }

        return $actions;
    }
}

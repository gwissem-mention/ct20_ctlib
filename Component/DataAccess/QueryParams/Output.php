<?php
namespace CTLib\Component\DataAccess\QueryParams;

class Output
{
    /**
     * Creates the needed array for the dataProvider class based on the
     * passed array of params
     */
    public static function paramsToDataProviderFilters($params)
    {
        return array_map(function ($param) {
            return [
                'field' => $param->field,
                'op'    => $param->op,
                'value' => $param->value
            ];
        }, $params);
    }
}

<?php

namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr;
use CTLib\Component\HttpFoundation\JsonResponse;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 * @author Sean Hunter <shunter@celltrak.com>
 */
class JsonDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];

    /**
     * @var array
     */
    protected $model = [];

    /**
     * {@inheritdoc}
     *
     * @param DataInputInterface $input
     *
     */
    public function start(DataInputInterface $input)
    {
        $this->model = $input->getModel();
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


        $this->records[] = $this->transform($record);
    }

    /**
     * {@inheritdoc}
     *
     * @param DataInputInterface $input
     *
     * @return array
     */
    public function end(DataInputInterface $input)
    {

        return new JsonResponse(
                array(  'data' => $this->records,
                        'model' => $this->getModelAliases()));
    }

    /**
     * {@inheritdoc}
     *
     * Perform the necessary processing to create a flat
     * Record of field values.
     *
     * @param mixed $document   Document data retrieved from API
     *
     * @return array
     */
    public function transform($document)
    {
        $processedRecord = [];

        foreach ($this->model as $fieldKey => $fieldValue) {

            // Do whatever we gotta do here (read values from
            // JSON activity document, or hand-off to callback)...
            if (is_callable($fieldValue)) {
                // Hand-off to callback to get field value.
                $value = call_user_func_array(
                    $fieldValue,
                    [
                        $document
                    ]
                );

            } else {
                $value = $this->documentDrillDown($fieldValue, $document);

            }

            $processedRecord[] = $value;
        }

        return $processedRecord;
    }

    /**
     *
     * Based on the field token pull the end value from the nested objects.
     * Record of field values.
     *
     * @param string $field
     * @param mixed $document   Document data retrieved from API
     *
     * @return mixed
     */
    private function documentDrillDown($field, $document)
    {
        if (!strpos($field, '.')) {
            // get top level attribute
            return Arr::mustGet($field, $document);
        }

        // find attribute in child objects.
        $value = null;

        $fieldTokens    = explode('.', $field);

        $parentDocObject = null;
        // loop into child objects
        foreach ($fieldTokens as $docObjectName) {
            if ($docObjectName == end($fieldTokens)){
                // hit bottom, get value
                $value = Arr::mustGet($docObjectName, $parentDocObject);
                break;
            }
            if (!$parentDocObject) {
                $parentDocObject = $document[$docObjectName];

            } else {
                $parentDocObject = $parentDocObject[$docObjectName];

            }
        }

        return $value;

    }

    /**
     *
     * Build data model return using the alias values expected at the UI level.
     *
     *
     * @return array
     */
    private function getModelAliases()
    {
        $aliases = [];
        foreach ($this->model as $fieldKey => $fieldValue) {
            $aliases[] = $fieldKey;
        }
        return $aliases;
    }

}

<?php
namespace CTLib\Component\HttpFoundation;

/**
 * Class of response that generates downloadable CSV file
 *
 */
class CsvResponse extends DownloadableResponse
{

    public function __construct($csvContent, $fileName, $destination = null)
    {
        try {
            if (is_array($csvContent)) {
                // convert array into string.
                $csvContent = array_reduce(
                    $csvContent,
                    function($result, $item) {
                        if (is_array($item)) {
                            $item = implode(",", $item);
                        }
                        
                        $result .= $item . "\n";
                        return $result;
                    },
                    ""
                );
            }
        }
        catch (\Exception $e) {
            throw \Exception("csv content is not correct");
        }

        if (!is_string($csvContent)) {
            throw \Exception("csv content is not correct");
        }

        return parent::__construct(
            (string)$csvContent,
            $fileName,
            mb_strlen($csvContent),
            'text/csv',
            $destination ?: DownloadableResponse::DESTINATION_ATTACHMENT
        );
    }

}

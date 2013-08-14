<?php
namespace CTLib\Component\HttpFoundation;

/**
 * Class to download csv file
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
class CsvFileResponse extends BinaryFileResponse
{
    /**
     * Constructor.
     *
     * @param SplFileInfo|string $file               The file to stream
     * @param Request            $request            Request
     * @param array              $headers            An array of response headers
     * @param null|string        $contentDisposition The type of Content-Disposition to set automatically with the filename
     */
    public function __construct($request, $file, $downloadName = '', $contentDisposition = null)
    {
        parent::__construct(
            $request,
            $file,
            $downloadName,
            array('Content-type' => 'text/csv'),
            $contentDisposition ?: BinaryFileResponse::DISPOSITION_ATTACHMENT
        );
    }
}
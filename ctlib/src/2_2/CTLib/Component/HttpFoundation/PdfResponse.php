<?php
namespace CTLib\Component\HttpFoundation;

/**
 * Class of response that generates downloadable PDF file
 *
 */
class PdfResponse extends DownloadableResponse
{

    public function __construct($pdfContent, $fileName, $destination)
    {
        return parent::__construct(
            (string)$pdfContent,
            $fileName,
            null,
            'application/pdf',
            $destination
        );
    }

}

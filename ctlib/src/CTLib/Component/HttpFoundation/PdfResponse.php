<?php
namespace CTLib\Component\HttpFoundation;

/**
 * Class of response that generates PDF
 *
 */
class PdfResponse extends \Symfony\Component\HttpFoundation\Response
{
    const DESTINATION_INLINE     = "inline";
    const DESTINATION_ATTACHMENT = "attachment"; //this will force download dialog

    public function __construct($pdfContent, $fileName, $destination)
    {
        $header = array(
            'Content-type' => 'application/pdf',
			'Cache-control' => 'private'
        );

        if ($destination == static::DESTINATION_INLINE) {
            $header['Content-Disposition'] = static::DESTINATION_INLINE;
        }
        elseif ($destination == static::DESTINATION_ATTACHMENT) {
            $header['Content-Disposition'] = static::DESTINATION_ATTACHMENT;
        }
        else {
            throw new \Exception("response destination is not supported");
        }

        $header['Content-Disposition'] .= ';filename="' . $fileName . '"';

        return parent::__construct(
            (string)$pdfContent,
            200,
            $header
        );
    }

}

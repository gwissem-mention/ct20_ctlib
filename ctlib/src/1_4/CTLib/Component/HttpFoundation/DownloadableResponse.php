<?php
namespace CTLib\Component\HttpFoundation;

/**
 * Class of response that generates downloadable contents
 *
 */
class DownloadableResponse extends \Symfony\Component\HttpFoundation\Response
{
    const DESTINATION_INLINE     = "inline";
    const DESTINATION_ATTACHMENT = "attachment"; //this will force download dialog


    public function __construct($fileContent, $fileName,
        $fileSize, $contentType, $destination)
    {
        $header = array(
            'Content-type'   => $contentType,
            'Cache-control'  => 'no-store',
            'Pragma'         => 'public',
            'Expires'        => 0
        );

        if (!empty($fileSize)) {
            $header['Content-Length'] = $fileSize;
        }

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
            (string)$fileContent,
            200,
            $header
        );
    }
}
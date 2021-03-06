<?php

namespace CTLib\Component\DataAccess;

use CTLib\Component\HttpFoundation\PdfResponse;
use CTLib\Component\Pdf\HtmlToPdf;

/**
 * Facilitates retrieving and processing nosql
 * results into pdf output.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class PdfDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];

    /**
     * @var $template
     */
    protected $template;

    /**
     * @var HtmlToPdf
     */
    protected $htmlToPdf;

    /**
     * @var Templating Engine
     */
    protected $templating;


    /**
     * @param string         $template
     * @param HtmlToPdf      $htmlToPdf
     * @param Templating     $templating
     */
    public function __construct($template, $htmlToPdf, $templating)
    {
        $this->template   = $template;
        $this->htmlToPdf  = $htmlToPdf;
        $this->templating = $templating;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function start(array $fields)
    {
        $this->records = [];
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
        $this->records[] = $record;
    }

    /**
     * {@inheritdoc}
     *
     * @param int $count
     *
     */
    public function end($count = 0)
    {
        $html = '';

        foreach ($this->records as $record) {
            $html .= $this->templating->render($this->template, $record);
        }

        $content = $this->htmlToPdf->renderPdf($html);

        return new PdfResponse(
            $content,
            "celltrak".date("YmdHis").".pdf",
            PdfResponse::DESTINATION_ATTACHMENT
        );
    }
}

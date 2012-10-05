<?php
namespace CTLib\Component\Pdf;

/**
 * CellTrak wrapper class to convert Html to PDF
 * using third party solution.
 *
 */
class HtmlPdf
{
    protected $html;
    
    public function __construct($html)
    {
        $this->html = $html;
    }

    /**
     * Genereates PDF Using third party html-to-pdf solution
     *
     * @return string PDF string
     *
     */
    public function render()
    {
        $pdf = new \DOMPDF;
        $pdf->load_html($this->html);
        $pdf->render();
        return $pdf->output();
    }

    /**
     * conver to pdf string
     *
     * @return string PDF string
     *
     */
    public function __toString()
    {
        return $this->render();
    }
}
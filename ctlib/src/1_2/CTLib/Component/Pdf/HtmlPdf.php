<?php
namespace CTLib\Component\Pdf;

/**
 * CellTrak wrapper class to convert Html to PDF
 * using third party DOMPDF solution.
 *
 */
class HtmlPdf
{

    protected $domPdf = null;
    protected $html2Pdf = null;

    public function __construct($kernel, $orientation = 'P')
    {
        $rootDir = $kernel->getRootDir();
        require_once $rootDir . '/../vendor/html2pdf/html2pdf.php';
        $this->html2Pdf = new \HTML2PDF($orientation, 'A4', 'en');
/*
        require_once $rootDir . '/../vendor/dompdf/dompdf_config.inc.php';
        global $_dompdf_warnings, $_dompdf_show_warnings, $_dompdf_debug, $_DOMPDF_DEBUG_TYPES, $memusage;
        $_dompdf_warnings = array();
        $this->domPdf = new \DOMPDF;
*/
    }

    /**
     * Genereates PDF Using third party html-to-pdf solution
     *
     * @return string PDF string
     *
     */
    public function render($html)
    {
        $this->html2Pdf->writeHTML($html);
        return $this->html2Pdf->Output('', 'S');

        /*
        $this->domPdf->load_html($html);
        $this->domPdf->render();
        return $this->domPdf->output();
        */
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
    
    
    public function __destruct()
    {
        unset($this->domPdf);
    }
}
<?php
namespace CTLib\Component\Pdf;

/**
 * CellTrak wrapper class to convert Html to PDF
 * using third party solution.
 *
 */
class HtmlPdf
{
    protected $kernel;
    
    public function __construct($kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Genereates PDF Using third party html-to-pdf solution
     *
     * @return string PDF string
     *
     */
    public function render($html)
    {
        $rootDir = $this->kernel->getRootDir();
        require_once $rootDir . '/../vendor/dompdf/dompdf_config.inc.php';
        $pdf = new \DOMPDF;
        $pdf->load_html($html);
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
<?php
namespace CTLib\Component\Pdf;

use CanGelis\PDF\PDF;


/**
 * CellTrak wrapper class to convert Html to PDF using third party
 * CanGelis PDF solution.
 *
 * For more on CanGelis, PDF, https://packagist.org/packages/cangelis/pdf.
 * CanGelis PDF requires the wkhtmltopdf binary. For more, http://wkhtmltopdf.org.
 *
 * @author Mike Turoff
 */
class HtmlToPdf
{

    /**
     * CanGelis\PDF\PDF
     * @var $pdf
     */
    protected $pdf;


    /**
     * @param string $wkhtmltopdfBinPath    CanGelis PDF requires the 
     *                                      wkhtmltopdf binary.
     */
    public function __construct($wkhtmltopdfBinPath)
    {
        $this->wkhtmltopdfBinPath = $wkhtmltopdfBinPath;        
    }

    /**
     * Genereates PDF Using third party html-to-pdf solution
     *
     * @return string PDF string
     */
    public function renderPdf($html)
    {
        $pdf = new PDF($this->wkhtmltopdfBinPath);
        $pdf->loadHTML($html);
        $pdf->pageSize('Letter');

        return $pdf->get();
    }

}
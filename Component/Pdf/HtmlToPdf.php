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
     * Plugin method attributes
     */
    const ORIENTATION_PORTRAIT = 'Portrait';
    const ORIENTATION_LANDSCAPE = 'Landscape';

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
    public function renderPdf($html, $orientation = self::ORIENTATION_PORTRAIT)
    {
        $pdf = new PDF($this->wkhtmltopdfBinPath);
        $pdf->loadHTML($html);
        $pdf->pageSize('Letter');
        $pdf->encoding('UTF-8');
        $pdf->orientation($orientation);

        return $pdf->get();
    }

}

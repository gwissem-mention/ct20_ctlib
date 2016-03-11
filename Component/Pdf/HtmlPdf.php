<?php
namespace CTLib\Component\Pdf;

use CanGelis\PDF\PDF;
use League\Flysystem\Adapter\Local as LocalFileAdapter;


/**
 * CellTrak wrapper class to convert Html to PDF
 * using third party CanGelis PDF solution.
 *
 * NOTE: This API isn't very well designed. Primarily, there's a disconnect
 * between the render and save methods, despite save requiring render to have
 * been called first. Right now (Mar 11, 2016), I'm just updating to use the
 * CanGelis PDF library. I don't want to have to update all the callers to use
 * a new API. We can come back "later" and polish this.
 *
 * @author Shuang Liu
 * @author Mike Turoff
 */
class HtmlPdf
{

    const WKHTMLTOPDF_BIN_PATH = '/usr/local/bin/wkhtmltopdf';


    /**
     * CanGelis\PDF\PDF
     * @var $pdf
     */
    protected $pdf;


    /**
     * @param AppKernel $kernel
     */
    public function __construct($kernel)
    {
        $this->pdf = new PDF(self::WKHTMLTOPDF_BIN_PATH);        
    }

    /**
     * Genereates PDF Using third party html-to-pdf solution
     *
     * @return string PDF string
     */
    public function render($html)
    {
        $this->pdf->loadHTML($html);
        $this->pdf->pageSize('Letter');

        return $this->pdf->get();
    }

    /**
     * Saves PDF to file on local filesystem.
     *
     * @param string $savePath
     */
    public function save($savePath)
    {
        $fileInfo = new \SPLFileInfo($savePath);
        $path = $fileInfo->getPath();
        $filename = $fileInfo->getFilename();

        $saveAdapter = new LocalFileAdapter($path);
        $this->pdf->save($filename, $saveAdapter);
    }

    /**
     * Outputs rendered PDF.
     *
     * @return string PDF string
     */
    public function __toString()
    {
        return $this->pdf->get();
    }   

}
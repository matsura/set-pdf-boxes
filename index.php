<?php

require_once './vendor/autoload.php';

use Mpdf\Mpdf;
use ZendPdf\PdfDocument;

function milimetersToPoints($mm) {

    return round($mm * 2.834646, 2);
}

function writeLine($x1, $y1, $x2, $y2, $h) {

    $k = 72 / 25.4;
    return sprintf("%.2F %.2F m %.2F %.2F 1 S", $x1 * $k, ($h - $y1) * $k, $x2 * $k, ($h - $y2) * $k);
}

$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];
$tempDir = $defaultConfig['tempDir'];
$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$pageWidth = 297;
$pageHeight = 210;

$mpdf = new Mpdf(array_merge($defaultConfig, [
    'title' => 'Just Listed MArketing Task',
    'margin_top'    => 0,
    'margin_left'   => 0,
    'margin_right'  => 0,
    'margin_bottom' => 0,
    'mode'          => 'utf-8',
    'format'        => [$pageWidth, $pageHeight],
    'img_dpi'       => 300,
    'display_mode'  => 'fullpage',
]));

$html = file_get_contents('views/front.html');

$mpdf->writeHTML($html);
$mpdf->Output('test.pdf', 'f');

$bleedInMM = 10; // the bleed in mm on each side
$pdfWidthInMM = $pageWidth;
$pdfHeightInMM = $pageHeight;

//width and height of new pdf. the value of $bleedInMM is doubled to have the bleed on both sides of the page
$newWidth = $pdfWidthInMM + ($bleedInMM * 2);
$newHeight = $pdfHeightInMM + ($bleedInMM * 2);

$srcPdfFilePath = 'test.pdf';

// make the crop line a little shorter so they don't touch each other
$cropLineLength = $bleedInMM - 1;

$stringOutput = $mpdf->Output($srcPdfFilePath, 's');

$lines = explode("\n", $stringOutput);
$lines = array_filter($lines, function ($line) {
    return strpos($line, '/MediaBox') === false
        && strpos($line, '/TrimBox') === false
        && strpos($line, '/CropBox') === false
        && strpos($line, '/BleedBox') === false;
});
// MediaBox should be equal to 'format' in points
$mediaBoxWidth = milimetersToPoints(297);
$mediaBoxHeight = milimetersToPoints(210);
// CropBox must include cropmarks so size = MediaBox
$cropBoxWidth = milimetersToPoints(297);
$crobBoxHeight = milimetersToPoints(210);
// TrimBox is without bleed
$trimBoxWidth = milimetersToPoints(287);
$trimBoxHeight = milimetersToPoints(200);
// BleedBox should be trimBox + bleedValue
$bleedBoxWidth = milimetersToPoints(297);
$bleedBoxHeight = milimetersToPoints(210);

$bleedValueInPoint = milimetersToPoints($bleedInMM);

for ($i = 0; $i < count($lines); $i++) {

    if (strpos($lines[$i], '/Parent') !== false) {
        // insert mediabox, cropbox after this one
        array_splice($lines, $i + 1, 0, "/MediaBox [0 0 {$mediaBoxWidth} {$mediaBoxHeight}]");
        array_splice($lines, $i + 2, 0, "/CropBox [0 0 {$cropBoxWidth} {$crobBoxHeight}]");
        array_splice($lines, $i + 3, 0, "/BleedBox [0 0 {$bleedBoxWidth} {$bleedBoxHeight}]");
        array_splice($lines, $i + 4, 0, "/TrimBox [{$bleedValueInPoint} {$bleedValueInPoint} {$trimBoxWidth} {$trimBoxHeight}]");
    }
}

$pdf = implode("\n", $lines);
file_put_contents($srcPdfFilePath, $pdf);

exec("qpdf --replace-input $srcPdfFilePath");

$zendPdf = PdfDocument::load($srcPdfFilePath);

$bleedInPoints = milimetersToPoints($bleedInMM);
$cropLineLengthInPoints = milimetersToPoints($cropLineLength);
$newWidthInPoints = milimetersToPoints($pageWidth);
$newHeightInPoints = milimetersToPoints($pageHeight);

foreach ($zendPdf->pages as $page) {

    $page->setLineWidth(0.75);

    $page->drawLine($bleedInPoints, 0, $bleedInPoints, $cropLineLengthInPoints); // horizontal top left
    $page->drawLine(0, $bleedInPoints, $cropLineLengthInPoints, $bleedInPoints); // vertical top left

    // top right crop marks
    $page->drawLine($newWidthInPoints - $bleedInPoints, 0, $newWidthInPoints - $bleedInPoints, $cropLineLengthInPoints); // horizontal top right
    $page->drawLine($newWidthInPoints - $cropLineLengthInPoints, $bleedInPoints, $newWidthInPoints, $bleedInPoints); // vertical top right

    // bottom left crop marks
    $page->drawLine(0, $newHeightInPoints - $bleedInPoints , $cropLineLengthInPoints , $newHeightInPoints - $bleedInPoints ); // horizontal bottom left
    $page->drawLine($bleedInPoints , $newHeightInPoints - $cropLineLengthInPoints , $bleedInPoints , $newHeightInPoints ); // vertical bottom left

    // bottom right crop marks
    $page->drawLine($newWidthInPoints - $cropLineLengthInPoints , $newHeightInPoints - $bleedInPoints , $newWidthInPoints , $newHeightInPoints - $bleedInPoints ); // horizontal top right
    $page->drawLine($newWidthInPoints - $bleedInPoints , $newHeightInPoints - $cropLineLengthInPoints , $newWidthInPoints - $bleedInPoints , $newHeightInPoints ); // vertical top right
}

$zendPdf->save($srcPdfFilePath);

<?php

require_once './vendor/autoload.php';

use Mpdf\Mpdf;
use setasign\Fpdi\Fpdi;

function milimetersToPoints($mm) {

    return round($mm * 2.834646, 2);
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

$pdf = new Fpdi(
    $pdfWidthInMM > $pdfWidthInMM ? 'L' : 'P', // landscape or portrait?
    'mm',
    array(
        $newWidth,
        $newHeight
    ));

if (file_exists($srcPdfFilePath)){
    $pagecount = $pdf->setSourceFile($srcPdfFilePath);
} else {
    error_log("Error! file: ".$srcPdfFilePath." does not exist");
    return FALSE;
}

// make the crop line a little shorter so they don't touch each other
$cropLineLength = $bleedInMM - 1;

for($i = 1; $i <= $pagecount; $i++) {
    $tpl = $pdf->importPage($i);
    $pdf->addPage();
    $size = $pdf->getTemplateSize($tpl);

    $pdf->useTemplate($tpl, $bleedInMM, $bleedInMM, $pageWidth, $pageHeight, true);

    $pdf->SetLineWidth(0.25);

    // top left crop marks
    $pdf->Line($bleedInMM /* x */, 0 /* y */, $bleedInMM /* x */, $cropLineLength /* y */); // horizontal top left
    $pdf->Line(0 /* x */, $bleedInMM /* y */, $cropLineLength /* x */, $bleedInMM /* y */); // vertical top left

    // top right crop marks
    $pdf->Line($newWidth - $bleedInMM /* x */, 0 /* y */, $newWidth - $bleedInMM /* x */, $cropLineLength /* y */); // horizontal top right
    $pdf->Line($newWidth - $cropLineLength /* x */, $bleedInMM /* y */, $newWidth /* x */, $bleedInMM /* y */); // vertical top right

    // bottom left crop marks
    $pdf->Line(0 /* x */, $newHeight - $bleedInMM /* y */, $cropLineLength /* x */, $newHeight - $bleedInMM /* y */); // horizontal bottom left
    $pdf->Line($bleedInMM /* x */, $newHeight - $cropLineLength /* y */, $bleedInMM /* x */, $newHeight /* y */); // vertical bottom left

    // bottom right crop marks
    $pdf->Line($newWidth - $cropLineLength /* x */, $newHeight - $bleedInMM /* y */, $newWidth /* x */, $newHeight - $bleedInMM /* y */); // horizontal top right
    $pdf->Line($newWidth - $bleedInMM /* x */, $newHeight - $cropLineLength /* y */, $newWidth - $bleedInMM /* x */, $newHeight /* y */); // vertical top right
}

$destinationPdfFilePath = 'test_modified.pdf';

$pdf->Output($destinationPdfFilePath,'F');

$stringOutput = $pdf->Output($destinationPdfFilePath, 's');

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
$trimBoxWidth = milimetersToPoints(277);
$trimBoxHeight = milimetersToPoints(190);
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

file_put_contents($destinationPdfFilePath, $pdf);

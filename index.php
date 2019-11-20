<?php

require_once './vendor/autoload.php';

use Mpdf\Mpdf;

function milimetersToPoints($mm) {

    return round($mm * 2.834646, 2);
}

$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];
$tempDir = $defaultConfig['tempDir'];
$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new Mpdf(array_merge($defaultConfig, [
    'title' => 'Just Listed MArketing Task',
    'margin_top'    => 0,
    'margin_left'   => 0,
    'margin_right'  => 0,
    'margin_bottom' => 0,
    'mode'          => 'utf-8',
    'format'        => [297, 210],
    'img_dpi'       => 300,
    'display_mode'  => 'fullpage',
]));

$html = file_get_contents('views/front.html');

$mpdf->writeHTML($html);
$stringOutput = $mpdf->Output('test.pdf', 's');

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

$bleedValueInPoint = milimetersToPoints(10);

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

file_put_contents('test.pdf', $pdf);

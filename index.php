<?php

require_once './vendor/autoload.php';

use Mpdf\Mpdf;

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
    'orientation'   => 'P',
    'format'        => [226, 164],
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

for ($i = 0; $i < count($lines); $i++) {

    if (strpos($lines[$i], '/Parent') !== false) {
        // insert mediabox, cropbox after this one
        array_splice($lines, $i + 1, 0, '/MediaBox [0 0 500.630 430.882]');
        array_splice($lines, $i + 2, 0, '/CropBox [0 0 500.630 450.882]');
        array_splice($lines, $i + 3, 0, '/BleedBox [0 0 500.630 420.882]');
        array_splice($lines, $i + 4, 0, '/TrimBox [0 0 500.630 410.882]');
    }
}

$pdf = implode("\n", $lines);

file_put_contents('test.pdf', $pdf);

<?php

require __DIR__.'/../vendor/autoload.php';

$pdf = new Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
$pdf->loadHtml(file_get_contents(__DIR__.'/computation-guide.html'));
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pages = $pdf->getCanvas()->get_page_count();
file_put_contents(__DIR__.'/CVMS-Computation-Guide.pdf', $pdf->output());
echo 'Created CVMS-Computation-Guide.pdf | Pages: '.$pages.' | Bytes: '.filesize(__DIR__.'/CVMS-Computation-Guide.pdf').PHP_EOL;

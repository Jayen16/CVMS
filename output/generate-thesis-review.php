<?php
require __DIR__.'/../vendor/autoload.php';
$pdf = new Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
$pdf->loadHtml(file_get_contents(__DIR__.'/thesis-review-guide.html'));
$pdf->setPaper('A4', 'portrait');
$pdf->render();
file_put_contents(__DIR__.'/CVMS-Thesis-Review-Guide.pdf', $pdf->output());
echo 'Created CVMS-Thesis-Review-Guide.pdf | Pages: '.$pdf->getCanvas()->get_page_count().' | Bytes: '.filesize(__DIR__.'/CVMS-Thesis-Review-Guide.pdf').PHP_EOL;

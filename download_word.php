<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$phpWord = new PhpWord();

$section = $phpWord->addSection([
    'orientation' => 'landscape'
]);

$htmlTable = $_POST['table_html'] ?? '';

if (!empty($htmlTable)) {

    \PhpOffice\PhpWord\Shared\Html::addHtml(
        $section,
        $htmlTable,
        false,
        false
    );
}

$fileName = 'test.docx';

header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

$tempFile = tempnam(sys_get_temp_dir(), 'word');

$writer = IOFactory::createWriter($phpWord, 'Word2007');

$writer->save($tempFile);

readfile($tempFile);

unlink($tempFile);

exit;
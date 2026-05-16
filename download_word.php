<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Style\Cell;

$phpWord = new PhpWord();

$section = $phpWord->addSection([
    'orientation' => 'landscape'
]);

// м: создание таблицы Word
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 60
]);

// м: первая строка заголовков
$table->addRow(1400);

// м: № п/п
$table->addCell(1200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('№ п/п');

// м: порода
$table->addCell(2200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Порода');

// м: кличка
$table->addCell(2200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Кличка');

// м: пол (вертикальный текст)
$table->addCell(900, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR
])->addText('Пол');

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
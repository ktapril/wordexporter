<?php

// ini_set('display_errors', 1);
// error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Style\Cell;

// м: категории из формы
$categories = json_decode($_POST['categories'] ?? '[]', true);
// м: получение строк результатов
$rows = json_decode($_POST['rows'] ?? '[]', true);

$phpWord = new PhpWord();

$section = $phpWord->addSection([
    'orientation' => 'landscape'
]);

// м: стиль обычного текста таблицы
$tableTextStyle = [
    'name' => 'Times New Roman',
    'size' => 9
];

// м: стиль заголовков таблицы
$tableHeaderStyle = [
    'name' => 'Times New Roman',
    'size' => 9,
    'bold' => true
];

// м: выравнивание текста
$centerParagraph = [
    'alignment' => 'center'
];

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
])->addText('№ п/п', $tableHeaderStyle, $centerParagraph);

// м: порода
$table->addCell(2200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Порода', $tableHeaderStyle, $centerParagraph);

// м: кличка
$table->addCell(2200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Кличка', $tableHeaderStyle, $centerParagraph);

// м: пол (вертикальный текст)
$table->addCell(900, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR
])->addText('Пол', $tableTextStyle, $centerParagraph);

// м: тест объединения колонок
$table->addCell(3200, [
    'gridSpan' => 3,
    'valign' => 'center'
])->addText(
    'Результаты по категориям',
    $tableHeaderStyle,
    $centerParagraph
);

// м: вторая строка заголовков
$table->addRow(1200);

// м: продолжение вертикального объединения
$table->addCell(null, ['vMerge' => 'continue']);
$table->addCell(null, ['vMerge' => 'continue']);
$table->addCell(null, ['vMerge' => 'continue']);
$table->addCell(null, ['vMerge' => 'continue']);

// м: подкатегории
// м: динамический вывод категорий
foreach ($categories as $categoryName) {

    $table->addCell(1600, [
        'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR,
        'valign' => 'center'
    ])->addText(
        $categoryName,
        $tableTextStyle,
        $centerParagraph
    );
}

// м: вывод строк участников
foreach ($rows as $rowData) {

    $table->addRow();

    foreach ($rowData as $cellText) {

        $table->addCell(1400, [
            'valign' => 'center'
        ])->addText(
            $cellText,
            $tableTextStyle,
            $centerParagraph
        );
    }
}


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
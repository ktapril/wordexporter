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
$table->addRow(900);

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

// м: дата рождения
$table->addCell(1500, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR
])->addText('Дата рождения', $tableTextStyle, $centerParagraph);

// м: № клейма или микрочипа
$table->addCell(2200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('№ клейма или микрочипа', $tableHeaderStyle, $centerParagraph);

// м: № родословной
$table->addCell(1500, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR
])->addText('№ родословной', $tableTextStyle, $centerParagraph);

// м: № квалификационной книжки
$table->addCell(1500, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR
])->addText('№ квал. книжки', $tableTextStyle, $centerParagraph);

// м: владелец, проводник
$table->addCell(2500, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Владелец, проводник', $tableTextStyle, $centerParagraph);

// м: объединение колонок
$table->addCell(2200 * count($categories), [
    'gridSpan' => count($categories),
    'valign' => 'center'
])->addText('Результаты по категориям', $tableHeaderStyle, $centerParagraph);

// м: итоговый результат
$table->addCell(2200, [
    'gridSpan' => 2,
    'valign' => 'center'
])->addText('Итоговый результат', $tableHeaderStyle, $centerParagraph);

// м: Ф.И.О. инструктора
$table->addCell(1800, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Ф.И.О. инструктора', $tableHeaderStyle, $centerParagraph);

// м: вторая строка заголовков
$table->addRow(1200);

// м: продолжение вертикального объединения
for ($i = 0; $i < 9; $i++) {
    $table->addCell(null, ['vMerge' => 'continue']);
}

// м: динамический вывод категорий
foreach ($categories as $categoryName) {

    $table->addCell(1800, [
        'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR,
        'valign' => 'center'
    ])->addText($categoryName, $tableTextStyle,$centerParagraph);
}

// м: баллы и время
$table->addCell(2200, [
    'valign' => 'center'
])->addText('Баллы, время', $tableTextStyle, $centerParagraph);

// м: место
$table->addCell(1400, [
    'valign' => 'center'
])->addText('Место', $tableTextStyle, $centerParagraph);

// м: продолжение объединения инструктора
$table->addCell(null, [
    'vMerge' => 'continue'
]);

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


$fileName = 'results.docx';

header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

$tempFile = tempnam(sys_get_temp_dir(), 'word');

$writer = IOFactory::createWriter($phpWord, 'Word2007');

$writer->save($tempFile);

readfile($tempFile);

unlink($tempFile);

exit;
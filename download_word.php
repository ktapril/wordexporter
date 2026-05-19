<?php

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

require_once 'vendor/autoload.php';

// м: конфиг таблицы
$config = require 'app/Config/table_config.php';

require_once 'app/Helpers/WordStyleHelper.php';

require_once 'app/Services/TableBuilderService.php';

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
$tableTextStyle =
    WordStyleHelper::getTableTextStyle();

// м: стиль заголовков таблицы
$tableHeaderStyle =
    WordStyleHelper::getHeaderStyle();

// м: выравнивание текста
$centerParagraph =
    WordStyleHelper::getCenterParagraph();

// м: создание таблицы Word
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 60
]);

// м: первая строка заголовков
$table->addRow($config['row_heights']['header_top'], [
    'tblHeader' => true
]);


// м: № п/п
$table->addCell($config['column_widths']['number'], [
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
    'textDirection' => Cell::TEXT_DIR_BTLR
])->addText('Пол', $tableTextStyle, $centerParagraph);

// м: дата рождения
$table->addCell(900, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => Cell::TEXT_DIR_BTLR
])->addText('Дата рождения', $tableTextStyle, $centerParagraph);

// м: № клейма или микрочипа
$table->addCell(2200, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('№ клейма или микрочипа', $tableHeaderStyle, $centerParagraph);

// м: № родословной
$table->addCell(700, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => Cell::TEXT_DIR_BTLR
])->addText('№ родословной', $tableTextStyle, $centerParagraph);

// м: № квалификационной книжки
$table->addCell(900, [
    'vMerge' => 'restart',
    'valign' => 'center',
    'textDirection' => Cell::TEXT_DIR_BTLR
])->addText('№ квал. книжки', $tableTextStyle, $centerParagraph);

// м: владелец, проводник
$table->addCell(2500, [
    'vMerge' => 'restart',
    'valign' => 'center'
])->addText('Владелец, проводник', $tableTextStyle, $centerParagraph);

// м: объединение колонок (две строки текста в одной ячейке)
$cell = $table->addCell(2200 * count($categories), [
    'gridSpan' => count($categories),
    'valign' => 'center'
]);

$cell->addText('Результаты по категориям', $tableHeaderStyle, [
    'alignment' => 'center',
    'spaceAfter' => 0,
    'spaceBefore' => 0
]);

$cell->addText('баллы, время', $tableTextStyle, [
    'alignment' => 'center',
    'spaceAfter' => 0,
    'spaceBefore' => 0
]);

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
$table->addRow(1200, [
    'tblHeader' => true
]);


// м: продолжение вертикального объединения
for ($i = 0; $i < 9; $i++) {
    $table->addCell(null, ['vMerge' => 'continue']);
}

// м: динамический вывод категорий
foreach ($categories as $categoryName) {

    $table->addCell(900, [
        'textDirection' => Cell::TEXT_DIR_BTLR,
        'valign' => 'center'
    ])->addText(
        $categoryName,
        $tableTextStyle,
        $centerParagraph
    );
}

// м: баллы и время
$table->addCell(2200, [
    'valign' => 'center'
])->addText(
    'Баллы, время',
    $tableTextStyle,
    $centerParagraph
);

// м: место
$table->addCell(1400, [
    'valign' => 'center'
])->addText(
    'Место',
    $tableTextStyle,
    $centerParagraph
);

// м: продолжение объединения инструктора
$table->addCell(null, [
    'vMerge' => 'continue'
]);

// м: вывод строк участников
foreach ($rows as $rowData) {

    // м: высота строки для вертикального текста
    $table->addRow(1000);

    foreach ($rowData as $index => $cellText) {

        $cellStyle = [
            'valign' => 'center'
        ];

        // м: вертикальные колонки
        $verticalColumns = $config['vertical_columns'];

        // м: категории начинаются после 9 колонки
        $categoryStartIndex = 9;

        // м: количество категорий
        $categoryEndIndex = $categoryStartIndex + count($categories) - 1;

        // м: категории тоже вертикальные
        for ($i = $categoryStartIndex; $i <= $categoryEndIndex; $i++) {
            $verticalColumns[] = $i;
        }

        // м: вертикальный текст
        if (in_array($index, $verticalColumns)) {

            $cellStyle['textDirection'] =
                Cell::TEXT_DIR_BTLR;
        }

        // м: ширина по умолчанию
        $cellWidth = 1400;

        // м: узкие вертикальные колонки
        if (in_array($index, $verticalColumns)) {
            $cellWidth = 700;
        }

        $table->addCell($cellWidth, $cellStyle)
            ->addText(
                $cellText,
                $tableTextStyle,
                $centerParagraph
            );
    }
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
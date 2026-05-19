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

  // м: сервис построения таблицы
$tableBuilder = new TableBuilderService();

$table = $tableBuilder->build(
    $section,
    $categories,
    $rows,
    $config
);

// м: построение шапки таблицы
$tableBuilder->buildHeader(
    $table,
    $categories,
    $config
);

// м: стиль обычного текста таблицы
$tableTextStyle =
    WordStyleHelper::getTableTextStyle();

// м: стиль заголовков таблицы
$tableHeaderStyle =
    WordStyleHelper::getHeaderStyle();

// м: выравнивание текста
$centerParagraph =
    WordStyleHelper::getCenterParagraph();

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
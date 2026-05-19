<?php

// ini_set('display_errors', 1);
// error_reporting(E_ALL);

require_once 'vendor/autoload.php';

// м: конфиг таблицы
$config = require 'app/Config/table_config.php';

// м: helper со стилями
require_once 'app/Helpers/WordStyleHelper.php';

// м: сервис таблицы
require_once 'app/Services/TableBuilderService.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// м: категории из формы
$categories = json_decode($_POST['categories'] ?? '[]', true);

// м: получение строк результатов
$rows = json_decode($_POST['rows'] ?? '[]', true);

$phpWord = new PhpWord();

$section = $phpWord->addSection([
    'orientation' => 'landscape'
]);

// м: создание сервиса таблицы
$tableBuilder = new TableBuilderService();

// м: создание таблицы
$table = $tableBuilder->build(
    $section,
    $categories,
    $rows,
    $config
);

// м: построение шапки
$tableBuilder->buildHeader(
    $table,
    $categories,
    $config
);

// м: построение строк участников
$tableBuilder->buildRows(
    $table,
    $rows,
    $categories,
    $config
);

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
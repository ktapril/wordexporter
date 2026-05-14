<?php
require_once __DIR__ . '/auth.php';
requireAuth();

// получаем HTML таблицы из формы
$tableHtml = $_POST['table_html'] ?? '';

if (empty($tableHtml)) {
    die('Таблица не получена');
}

// отправка файла пользователю как Word-документ
header("Content-Type: application/msword; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"results.doc\"");

// обертка таблицы в мин HTML 
echo "<html><head><meta charset='UTF-8'></head><body>";
echo $tableHtml;
echo "</body></html>";
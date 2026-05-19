<?php

//принимает POST, декодирует данные, вызывает сервис

class ExportController
{
    public function handle(): void
    {
        $categories = json_decode(
            $_POST['categories'] ?? '[]',
            true
        );

        $rows = json_decode(
            $_POST['rows'] ?? '[]',
            true
        );

        if (empty($categories) || empty($rows)) {
            http_response_code(400);
            exit('Нет данных для экспорта');
        }

        $config = require __DIR__ . '/../Config/table_config.php';

        $service = new WordExportService();
        $service->export($categories, $rows, $config);
    }
}
<?php

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

//создает документ, секцию, сохраняет, отдает файл

class WordExportService
{
    public function export(
        array $categories,
        array $rows,
        array $config,
        ?string $templatePath = null
    ): void {

        $phpWord = new PhpWord();
        $section = $phpWord->addSection(['orientation' => 'landscape']);

        $tableBuilder = new TableBuilderService();
        $table = $tableBuilder->build($section, $categories, $rows, $config);
        $tableBuilder->buildHeader($table, $categories, $config);
        $tableBuilder->buildRows($table, $rows, $categories, $config);

        $tempFile = tempnam(sys_get_temp_dir(), 'word') . '.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        if ($templatePath !== null) {
            $absolutePath = __DIR__ . '/../../' . $templatePath; //// путь от корня проекта

            if (file_exists($absolutePath)) {
                $resultFile = $this->mergeWithTemplate($tempFile, $absolutePath);
                unlink($tempFile);
                $this->sendFile($resultFile);
                return;
            }
        }

        $this->sendFile($tempFile);
    }

    private function mergeWithTemplate(string $docxWithTable, string $templatePath): string
    {
        // чтение XML таблицы из врем. документа
        $zip = new ZipArchive();
        $zip->open($docxWithTable);
        $tableDocXml = $zip->getFromName('word/document.xml');
        $zip->close();

        // вырезка содержимое тела: всё между <w:body> и <w:sectPr>
        preg_match('/<w:body>(.*?)<w:sectPr/s', $tableDocXml, $matches);
        $tableBodyContent = $matches[1] ?? '';

        // копия шаблон
        $resultFile = tempnam(sys_get_temp_dir(), 'result') . '.docx';
        copy($templatePath, $resultFile);

        // вставка таблицы в тело шаблона, sectPr с колонтитулами не трогает
        $zip = new ZipArchive();
        $zip->open($resultFile);
        $templateDocXml = $zip->getFromName('word/document.xml');

        $newDocXml = preg_replace(
            '/(<w:body>).*?(<w:sectPr)/s',
            '$1' . $tableBodyContent . '$2',
            $templateDocXml
        );

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $newDocXml);
        $zip->close();

        return $resultFile;
    }

    private function sendFile(string $filePath): void
    {
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="results.docx"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        unlink($filePath);
        exit;
    }
}
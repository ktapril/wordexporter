<?php

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

//создает документ, секцию, сохраняет, отдает файл

class WordExportService
{
    public function export(
        array $categories,
        array $rows,
        array $config
    ): void {

        $phpWord = new PhpWord();

        $section = $phpWord->addSection([
            'orientation' => 'landscape'
        ]);

        $tableBuilder = new TableBuilderService();

        $table = $tableBuilder->build(
            $section,
            $categories,
            $rows,
            $config
        );

        $tableBuilder->buildHeader(
            $table,
            $categories,
            $config
        );

        $tableBuilder->buildRows(
            $table,
            $rows,
            $categories,
            $config
        );

        $this->sendFile($phpWord);
    }

    private function sendFile(PhpWord $phpWord): void
    {
        $fileName = 'results.docx';
        $tempFile = tempnam(sys_get_temp_dir(), 'word');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        readfile($tempFile);
        unlink($tempFile);
        exit;
    }
}
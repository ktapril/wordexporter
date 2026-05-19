<?php

use PhpOffice\PhpWord\Style\Cell;

//строит шапку и строки

class TableBuilderService
{
    // м: создание таблицы
    public function build(
        $section,
        $categories,
        $rows,
        $config
    ) {

        // м: создание таблицы Word
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 60
        ]);

        return $table;
    }

    // м: построение шапки таблицы
    public function buildHeader(
        $table,
        $categories,
        $config
    ) {

        $tableTextStyle =
            WordStyleHelper::getTableTextStyle();

        $tableHeaderStyle =
            WordStyleHelper::getHeaderStyle();

        $centerParagraph =
            WordStyleHelper::getCenterParagraph();

        // м: первая строка заголовков
        $table->addRow($config['row_heights']['header_top'], [
            'tblHeader' => true
        ]);

        // м: № п/п
        $table->addCell($config['column_widths']['number'], [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            '№ п/п',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: порода
        $table->addCell($config['column_widths']['breed'], [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            'Порода',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: кличка
        $table->addCell($config['column_widths']['nickname'], [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            'Кличка',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: пол
        $table->addCell($config['column_widths']['sex'], [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            'Пол',
            $tableTextStyle,
            $centerParagraph
        );

        // м: дата рождения
        $table->addCell($config['column_widths']['birthdate'], [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            'Дата рождения',
            $tableTextStyle,
            $centerParagraph
        );

        // м: № клейма или микрочипа
        $table->addCell($config['column_widths']['chip'], [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            '№ клейма или микрочипа',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: № родословной
        $table->addCell($config['column_widths']['pedigree'], [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            '№ родословной',
            $tableTextStyle,
            $centerParagraph
        );

        // м: № квалификационной книжки
        $table->addCell($config['column_widths']['qualification_book'], [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            '№ квал. книжки',
            $tableTextStyle,
            $centerParagraph
        );

        // м: владелец, проводник
        $table->addCell($config['column_widths']['owner'], [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            'Владелец, проводник',
            $tableTextStyle,
            $centerParagraph
        );

        // м: результаты по категориям
        $cell = $table->addCell( $config['column_widths']['category'] * count($categories),
            [
                'gridSpan' => count($categories),
                'valign' => 'center'
            ]
        );

        $cell->addText(
            'Результаты по категориям',
            $tableHeaderStyle,
            [
                'alignment' => 'center',
                'spaceAfter' => 0,
                'spaceBefore' => 0
            ]
        );

        $cell->addText(
            'баллы, время',
            $tableTextStyle,
            [
                'alignment' => 'center',
                'spaceAfter' => 0,
                'spaceBefore' => 0
            ]
        );

        // м: итоговый результат
        $table->addCell($config['column_widths']['result'], [
            'gridSpan' => 2,
            'valign' => 'center'
        ])->addText(
            'Итоговый результат',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: Ф.И.О. инструктора
        $table->addCell($config['column_widths']['instructor'], [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            'Ф.И.О. инструктора',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: вторая строка заголовков
        $table->addRow($config['row_heights']['header_bottom'], [
            'tblHeader' => true
        ]);

        // м: продолжение вертикального объединения
        for ($i = 0; $i < 9; $i++) {

            $table->addCell(null, [
                'vMerge' => 'continue'
            ]);
        }

        // м: категории
        foreach ($categories as $categoryName) {

            $table->addCell($config['column_widths']['category'], [
                'textDirection' => Cell::TEXT_DIR_BTLR,
                'valign' => 'center'
            ])->addText(
                $categoryName,
                $tableTextStyle,
                $centerParagraph
            );
        }

        // м: баллы и время
        $table->addCell($config['column_widths']['result'], [
            'valign' => 'center'
        ])->addText(
            'Баллы, время',
            $tableTextStyle,
            $centerParagraph
        );

        // м: место
        $table->addCell($config['column_widths']['place'], [
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
    }

    public function buildRows(
    $table,
    $rows,
    $categories,
    $config
) {

    $tableTextStyle =
        WordStyleHelper::getTableTextStyle();

    $centerParagraph =
        WordStyleHelper::getCenterParagraph();

    // м: вертикальные колонки (только из конфига, без категорий)
    $verticalColumns = $config['vertical_columns'];

    // м: категории начинаются после 9 колонки
    $categoryStartIndex = 9;

    // м: конец категорий
    $categoryEndIndex =
        $categoryStartIndex +
        count($categories) - 1;

    foreach ($rows as $rowData) {

        $table->addRow(
            $config['row_heights']['content']
        );

        foreach ($rowData as $index => $cellText) {

            $cellStyle = [
                'valign' => 'center'
            ];

            // м: вертикальный текст только для колонок из конфига
            if (in_array($index, $verticalColumns)) {
                $cellStyle['textDirection'] =
                    Cell::TEXT_DIR_BTLR;
            }

            // ширина ячейки
            if (in_array($index, $verticalColumns)) {
                // узкие вертикальные колонки (пол, дата и т.д.)
                $cellWidth =
                    $config['column_widths']['vertical_content'];

            } elseif ($index >= $categoryStartIndex && $index <= $categoryEndIndex) {
                // колонки категорий 
                $cellWidth =
                    $config['column_widths']['category'];

            } else {
                // обычные колонки
                $cellWidth =
                    $config['column_widths']['default_content'];
            }

            $cell = $table->addCell(
    $cellWidth,
    $cellStyle
);

// разбивка текста по переносам строк
$lines = explode("\n", $cellText);

foreach ($lines as $line) {
    $cell->addText(
        trim($line),
        $tableTextStyle,
        $centerParagraph
    );
}
        }
    }
}
}
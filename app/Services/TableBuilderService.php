<?php

use PhpOffice\PhpWord\Style\Cell;

class TableBuilderService
{
    // создание таблицы
    public function build(
        $section,
        $categories,
        $rows,
        $config
    ) {

        // создание таблицы Word
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 60
        ]);

        return $table;
    }

    // построение шапки таблицы
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
        $table->addCell(2200, [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            'Порода',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: кличка
        $table->addCell(2200, [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            'Кличка',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: пол
        $table->addCell(900, [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            'Пол',
            $tableTextStyle,
            $centerParagraph
        );

        // м: дата рождения
        $table->addCell(900, [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            'Дата рождения',
            $tableTextStyle,
            $centerParagraph
        );

        // м: № клейма или микрочипа
        $table->addCell(2200, [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            '№ клейма или микрочипа',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: № родословной
        $table->addCell(700, [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            '№ родословной',
            $tableTextStyle,
            $centerParagraph
        );

        // м: № квалификационной книжки
        $table->addCell(900, [
            'vMerge' => 'restart',
            'valign' => 'center',
            'textDirection' => Cell::TEXT_DIR_BTLR
        ])->addText(
            '№ квал. книжки',
            $tableTextStyle,
            $centerParagraph
        );

        // м: владелец, проводник
        $table->addCell(2500, [
            'vMerge' => 'restart',
            'valign' => 'center'
        ])->addText(
            'Владелец, проводник',
            $tableTextStyle,
            $centerParagraph
        );

        // м: результаты по категориям
        $cell = $table->addCell(
            900 * count($categories),
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
        $table->addCell(2200, [
            'gridSpan' => 2,
            'valign' => 'center'
        ])->addText(
            'Итоговый результат',
            $tableHeaderStyle,
            $centerParagraph
        );

        // м: Ф.И.О. инструктора
        $table->addCell(1800, [
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
    }
}
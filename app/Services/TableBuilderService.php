<?php

use PhpOffice\PhpWord\Style\Cell;

class TableBuilderService
{
    // построение таблицы
    public function build(
        $section,
        $categories,
        $rows,
        $config
    ) {

        $tableTextStyle =
            WordStyleHelper::getTableTextStyle();

        $tableHeaderStyle =
            WordStyleHelper::getHeaderStyle();

        $centerParagraph =
            WordStyleHelper::getCenterParagraph();

        // создание таблицы Word
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 60
        ]);

        // позже будет buildHeader()
        // позже будет buildRows()
    }
}
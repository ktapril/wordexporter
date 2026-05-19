<?php

//стили 

class WordStyleHelper
{
    // стиль обычного текста таблицы
    public static function getTableTextStyle()
    {
        return [
            'name' => 'Times New Roman',
            'size' => 9
        ];
    }

    // стиль заголовков таблицы
    public static function getHeaderStyle()
    {
        return [
            'name' => 'Times New Roman',
            'size' => 9,
            'bold' => true
        ];
    }

    // выравнивание текста
    public static function getCenterParagraph()
    {
        return [
            'alignment' => 'center',
            'spaceAfter' => 0,
            'spaceBefore' => 0
        ];
    }
}
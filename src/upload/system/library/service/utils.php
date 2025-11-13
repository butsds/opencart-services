<?php

namespace Service;

class Utils
{
    public static function clean($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[self::clean($key)] = self::clean($value);
            }
        } elseif (is_string($data)) {
            return htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
        }

        //TODO: add object

        return $data;
    }

    public static function unclean($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[self::unclean($key)] = self::unclean($value);
            }
        } elseif (is_string($data)) {
            return html_entity_decode($data, ENT_QUOTES, "UTF-8");
        }

        //TODO: add object

        return $data;
    }

    public static function ruTranslit($text)
    {
        $rus = array("а", "А", "б", "Б", "в", "В", "г", "Г", "д", "Д", "е", "Е", "ё", "Ё", "ж", "Ж", "з", "З", "и", "И", "й", "Й", "к", "К", "л", "Л", "м", "М", "н", "Н", "о", "О", "п", "П", "р", "Р", "с", "С", "т", "Т", "у", "У", "ф", "Ф", "х", "Х", "ц", "Ц", "ч", "Ч", "ш", "Ш", "щ",  "Щ", "ъ", "Ъ", "ы", "Ы", "ь", "Ь", "э", "Э", "ю", "Ю", "я", "Я", '/', ' ', '—');
        $eng = array("a", "A", "b", "B", "v", "V", "g", "G", "d", "D", "e", "E", "e", "E", "zh", "ZH", "z", "Z", "i", "I", "j", "J", "k", "K", "l", "L", "m", "M", "n", "N", "o", "O", "p", "P", "r", "R", "s", "S", "t", "T", "u", "U", "f", "F", "h", "H", "c", "C", "ch", "CH", "sh", "SH", "sch", "SCH", "", "", "i", "I", "", "", "e", "E", "yu", "YU", "ya", "YA", '', '-', '-');
        $text = str_replace($rus, $eng, $text);
        return $text;
    }
}

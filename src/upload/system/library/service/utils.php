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

    public static function unclean($data) {
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
}
<?php

namespace app\hgrid\src\helpers;

class ModelObfuscator
{
    /**
     * @param $string
     * @return string
     * Masks the strings
     * How it does:
     *
     * Characters are mapped in reverse order from half of the alphabetical series
     * that is from a-m -> z-n
     * and from n-z -> a-m
     */
    public static function mask($string)
    {
        $_alphabetsStart13 = range('a', 'm');
        $_mapStart13 = range('z', 'n');

        $_alphabetMapStart13 = array_combine($_alphabetsStart13, $_mapStart13);

        $_alphabetsEnd13 = range('n', 'z');
        $_mapEnd13 = range('a', 'm');

        $_alphabetMapEnd13 = array_combine($_alphabetsEnd13, $_mapEnd13);

        $_specials = ['/' => '_1', '\\' => '_2'];

        $_temp = [];
        $_stringArray = str_split($string);
//        $repetitionCountOfString = array_count_values($_stringArray);

        $traversalCount = 0;

        foreach ($_stringArray as $s) {
            $traversalCount++;
            if (isset($_alphabetMapStart13[$s])) {
                $_temp[] = $_alphabetMapStart13[$s];
            } else if (isset($_alphabetMapEnd13[$s])) {
                $_temp[] = $_alphabetMapEnd13[$s];
            } else if (isset($_specials[$s])) {
                $_temp[] = ModelObfuscator . phpchr(rand(65, 90));
            } else {
                $_temp[] = $s;
            }
        }
        return '{"' . self::shortName($string) . '_' . implode('', $_temp) . substr(hash('sha256', $string), 10, 10) . '"}';
    }

    /**
     * @param $path
     * @return string|null
     * Extracts the shortName from the class model
     */
    public static function shortName($path): ?string
    {
        if (!empty($path)) {
            $classPath = $path;
            $lastBackslashIndex = strrpos($classPath, '\\');
            return substr($classPath, $lastBackslashIndex + 1);
        }
        return null;
    }
}
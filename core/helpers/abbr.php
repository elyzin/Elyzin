<?php

/**
 * Generate 3-letter abbreviation
 * @author effone <me@eff.one>
 * 
 * @param string $string
 * @return string
 */

return function ($string = '')
{
    $vowels = array('A', 'E', 'I', 'O', 'U');
    $string = preg_replace('/[^A-Z ]/u', '', strtoupper(strip_tags($string))); // Sanitize, capitalize, remove numbers and special characters
    $str_array = explode(' ', $string);
    if (sizeof($str_array) > 2) {
        $string = '';
        foreach ($str_array as $word) {
            $string .= substr($word, 0, 1);
        }
    } else if (sizeof($str_array) == 2) {
        $string = substr($str_array[0], 0, 1);
        $string .= substr($str_array[1], 0, 1) . substr($str_array[1], -1);
    } else {
        $first_char = substr($string, 0, 1);
        $last_char = substr($string, -1);
        $last_char = in_array($last_char, $vowels) ? substr($string, -2, 1) : $last_char; // Consider second last if last one is vowel
        $string = str_replace($vowels, '', substr($string, 1, -1)); // Removefirst and last character, all vowels and spaces from middle section of string
        $string = strlen($string) > 1 ? substr($string, (ceil(strlen($string) / 2) - 1), 1) : $string;
        $string = $first_char . $string . $last_char;
    }
    $string = strlen($string) > 3 ? abb($string) : $string; // Re-abbreviate if character is more than 3
    return $string;
};
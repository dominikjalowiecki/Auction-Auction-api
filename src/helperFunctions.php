<?php

/**
 * Word aligned text truncate to specified length.
 * 
 * @param   string  $text Text to truncate
 * @param   int     $length Length that text is truncated to
 * @param   string  $suffix optional Suffix for truncated text
 * @return  string  Truncated $text
 */
function smartTruncate($text, $length, $suffix = "...")
{
    if (strlen($text) <= $length) {
        return $text;
    } else {
        return implode(
            ' ',
            array_slice(
                explode(' ', substr($text, 0, $length)),
                0,
                -1
            )
        ) . $suffix;
    }
}

/**
 * Generate comma separated string from values of specified key in nested arrays
 * 
 * @param   array   $arr Array containing arrays to process
 * @param   mixed   $key Key of values from nested arrays to generate string from
 * @return  string  String of combined $key values
 */
function nestedArraysKeyValuesToString(&$arr, $key)
{
    $in_values = "";
    $i = 0;
    $n = count($arr);
    for ($i; $i < $n - 1; $i++)
        $in_values .= $arr[$i][$key] . ", ";
    $in_values .= $arr[$i][$key];

    return $in_values;
}

/**
 * Find all elements (arrays) of array with given value of specified key
 * 
 * @param   array   $arr Array of arrays to process
 * @param   mixed   $key Lookup key of nested array
 * @param   mixed   $value Searched value
 * @return  array   Array of results
 */
function binarySearchGetArrays(&$arr, $key, $value)
{
    $arr_length = count($arr);
    $l = 0;
    $r = $arr_length - 1;
    $i = $l + $r; // Index of first occurance

    if ($arr_length == 0)
        return [];

    while ($l != $r) {
        $i = intdiv($l + $r, 2);
        if ($arr[$i][$key] >= $value) {
            $r = $i;
        } else {
            $l = $i + 1;
        }
    }

    $res = [];
    for ($j = $i; $i < $arr_length && ($el = $arr[$j])[$key] == $value; $j++)
        $res[] = $el;

    return $res;
}

/**
 * Removes an item from the array and returns its value.
 *
 * @param   array   $arr The input array
 * @param   mixed   $key The key pointing to the desired value
 * @return  mixed   The value mapped to $key or null if none
 */
function array_remove(&$arr, $key)
{
    $val = $arr[$key] ?? null;
    unset($arr[$key]);

    return $val;
}

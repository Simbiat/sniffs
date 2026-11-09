<?php

declare(strict_types=1);

// === Every checked function, one argument short of the encoding slot ===
mb_check_encoding($value); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_chr($codepoint); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_convert_case($str, \MB_CASE_TITLE); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_convert_encoding($str, 'UTF-8'); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_convert_kana($str, 'KV'); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_decode_numericentity($str, $map); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_encode_numericentity($str, $map); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_http_output(); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_lcfirst($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_ltrim($str, $characters); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_ord($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_rtrim($str, $characters); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_scrub($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_str_pad($str, $length, ' ', \STR_PAD_RIGHT); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_str_split($str, $length); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strcut($str, $start, $length); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strimwidth($str, $start, $width, '...'); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_stripos($haystack, $needle, $offset); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_stristr($haystack, $needle, before_needle: false); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strlen($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strpos($haystack, $needle, $offset); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strrchr($haystack, $needle, before_needle: false); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strrichr($haystack, $needle, before_needle: false); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strripos($haystack, $needle, $offset); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strrpos($haystack, $needle, $offset); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strstr($haystack, $needle, before_needle: false); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strtolower($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strtoupper($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strwidth($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_substr($str, $start, $length); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_substr_count($haystack, $needle); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_trim($str, $characters); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_ucfirst($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding

// === Spelling variants of the function name are still the same function ===
MB_STRLEN($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
\mb_strlen($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strlen ($str); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding

// === Multi-line call (without trailing comma; see TrailingComma.inc for the other form) ===
mb_substr( // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
    $str,
    0,
    5
);

// === Nested calls: each call is judged on its own ===
mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8')); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strtoupper(mb_substr($str, 0, 1), 'UTF-8'); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding

// === Encoding already explicit: must NOT be flagged ===
$variable_a = mb_strlen($str, 'UTF-8');
$variable_b = mb_substr($str, 0, 5, encoding: 'UTF-8');
$variable_c = mb_substr($str, 0, 5, encoding : 'UTF-8');
$variable_x = mb_strlen($str, $variable_encoding);
$variable_y = mb_strlen($str, 'ISO-8859-1');
$variable_z = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
mb_http_output('UTF-8');
mb_str_pad($str, 10, ' ', \STR_PAD_RIGHT, 'UTF-8');

// === Not the global function: must NOT be flagged ===
$variable_h = $obj->mb_strlen($str);
$variable_i = $obj?->mb_strlen($str);
$variable_j = Foo::mb_strlen($str);
$variable_k = Foo\mb_strlen($str);
$variable_l = namespace\mb_strlen($str);

$anonymous = new class
{
    public function mb_strlen(string $str): int
    {
        return 0;
    }
};

// === Unrelated function with a similar name ===
mb_internal_encoding('UTF-8');
mb_strlen_custom($str);

// === Function name without a call ===
$callable = 'mb_strlen';

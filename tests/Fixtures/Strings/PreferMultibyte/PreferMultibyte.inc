<?php

declare(strict_types=1);

// === Single-byte form of each checked function ===
chr($codepoint); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
lcfirst($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
ltrim($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
ord($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
rtrim($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
str_pad($str, $length); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
str_split($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
stripos($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
stristr($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strpos($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strrchr($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strripos($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strrpos($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strstr($haystack, (string) $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strtolower($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strtoupper($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
substr($str, $start); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
substr_count($haystack, $needle); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
trim($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
ucfirst($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte

// === Spelling variants and first arguments that are not plain variables ===
STRLEN($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
\strlen($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen ($str); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen('literal'); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($this->name); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen(trim($str)); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte Simbiat.Strings.PreferMultibyte.PreferMultibyte
strtolower(\strtoupper($str)); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte Simbiat.Strings.PreferMultibyte.PreferMultibyte

// === First argument name says "binary data": must NOT be flagged ===
strlen($binary);
strlen($blob);
strlen($bytes);
strlen($raw);
strlen($hash);
strlen($digest);
strlen($checksum);
strlen($signature);
strlen($ciphertext);
strlen($plaintext);
strlen($salt);
strlen($iv);
strlen($key);
strlen($BINARY);
substr($hash, 0, 8);
trim($raw);

// === A hint can be one word of a longer name (camelCase, snake_case, plural): must NOT be flagged ===
strlen($rawBody);
strlen($user_key);
strlen($IV_BYTES);
strlen($ivBytes);
strlen($hashes);
strlen($keys);
strlen($saltValue);

// === A hint is a whole word only, not a part of a word: flagged ===
strlen($archive); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($private); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($active); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($monkey); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($userKeyboard); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($crawler); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
strlen($hashtag); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte

// === Only the FIRST argument is looked at ===
substr($str, $hash); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte
str_pad($str, $key); // @expect Simbiat.Strings.PreferMultibyte.PreferMultibyte

// === Already multibyte, or not the global function: must NOT be flagged ===
mb_strlen($str, 'UTF-8');
$variable_h = $obj->strlen($str);
$variable_i = $obj?->strlen($str);
$variable_j = Foo::strlen($str);
$variable_k = Foo\strlen($str);
$variable_l = namespace\strlen($str);

$anonymous = new class
{
    public function strlen(): int
    {
        return 0;
    }
};

// === Similar names / no call ===
strlen_custom($str);
strtolowercase($str);
$callable = 'strlen';

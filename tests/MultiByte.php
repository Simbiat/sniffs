<?php

// Suppressing some inspections, since this is a test file, and is not supposed to be "correct"
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter, SlevomatCodingStandard.Variables.UnusedVariable
// phpcs:disable Symfony.Commenting, PSR1.Files.SideEffects, Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.ControlStructures.LanguageConstructWithParentheses.UsedWithParentheses
// phpcs:disable SlevomatCodingStandard.Commenting, PSR12.Files.FileHeader.SpacingAfterDocblockBlock
// phpcs:disable PSR1.Classes.ClassDeclaration, SlevomatCodingStandard.Classes.RequireAbstractOrFinal
// phpcs:disable Universal.Classes.RequireFinalClass, SlevomatCodingStandard.Files.TypeNameMatchesFileName
// phpcs:disable Universal.PHP.DisallowExitDieParentheses, Universal.PHP.RequireExitDieParentheses
/** @noinspection PhpEnforceDocCommentInspection, PhpRedundantOptionalArgumentInspection, PhpUnusedParameterInspection */
/** @noinspection PhpMissingDocCommentInspection, PhpUndefinedVariableInspection, PhpUndefinedClassInspection */
/** @noinspection UnusedFunctionResultInspection, PhpExpressionResultUnusedInspection, AutoloadingIssuesInspection */
/** @noinspection PhpUnreachableStatementInspection, PhpIllegalPsrClassPathInspection, SqlResolveInspection */
/** @noinspection CompositionAndInheritanceInspection */

declare(strict_types=1);

// Simbiat.Strings.MultibyteEncoding: each call is one argument short of the encoding slot

mb_check_encoding($value);
mb_chr($codepoint);
mb_convert_case($str, MB_CASE_TITLE);
mb_convert_encoding($str, 'UTF-8');
mb_convert_kana($str, 'KV');
mb_decode_numericentity($str, $map);
mb_encode_numericentity($str, $map);
mb_http_output();
mb_lcfirst($str);
mb_ltrim($str, $characters);
mb_ord($str);
mb_rtrim($str, $characters);
mb_scrub($str);
mb_str_pad($str, $length, ' ', STR_PAD_RIGHT);
mb_str_split($str, $length);
mb_strcut($str, $start, $length);
mb_strimwidth($str, $start, $width, '...');
mb_stripos($haystack, $needle, $offset);
mb_stristr($haystack, $needle, false);
mb_strlen($str);
mb_strpos($haystack, $needle, $offset);
mb_strrchr($haystack, $needle, false);
mb_strrichr($haystack, $needle, false);
mb_strripos($haystack, $needle, $offset);
mb_strrpos($haystack, $needle, $offset);
mb_strstr($haystack, $needle, false);
mb_strtolower($str);
mb_strtoupper($str);
mb_strwidth($str);
mb_substr($str, $start, $length);
mb_substr_count($haystack, $needle);
mb_trim($str, $characters);
mb_ucfirst($str);

// Should NOT be flagged (encoding already explicit).
$variable_a = mb_strlen($str, 'UTF-8');
$variable_b = mb_substr($str, 0, 5, encoding: 'UTF-8');
$variable_x = mb_strlen($str, $variable_encoding);
$variable_y = mb_strlen($str, 'ISO-8859-1');

// Simbiat.Strings.PreferMultibyte: single-byte form of each

chr($codepoint);
lcfirst($str);
ltrim($str);
ord($str);
rtrim($str);
str_pad($str, $length);
str_split($str);
stripos($haystack, $needle);
stristr($haystack, $needle);
strlen($str);
strpos($haystack, $needle);
strrchr($haystack, $needle);
strripos($haystack, $needle);
strrpos($haystack, $needle);
strstr($haystack, $needle);
strtolower($str);
strtoupper($str);
substr($str, $start);
substr_count($haystack, $needle);
trim($str);
ucfirst($str);

// Should not be flagged due to argument names

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

// Should NOT be flagged (method call / different class, not the global function).
$variable_h = $obj->strlen($str);

class Foo
{
    public function strlen(string $s): int
    {
        return 0;
    }
}

<?php

declare(strict_types=1);

// === Missing Unicode flag: every checked function ===
preg_filter('/foo/', $replacement, $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_grep('/foo/', $array); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match('/foo/', $subject, $matches); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match_all('/foo/', $subject, $matches); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_replace('/foo/', $replacement, $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_replace_callback('/foo/', $callback, $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_split('/foo/', $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag

// === Missing Unicode flag, with other flags present ===
preg_match('/foo/i', $subject, $matches); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match('/foo/ims', $subject, $matches); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match('/foo/ir', $subject, $matches); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag

// === Other delimiters and quote styles ===
preg_match('#foo#', $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match('~foo~i', $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match("/foo/", $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match('/a\/b/', $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
preg_match('/a\/b/i', $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
PREG_MATCH('/foo/', $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag

// === Multi-line call: the result is reported on the line of the pattern ===
preg_match(
    '/foo/', // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
    $subject,
);

// === Unicode flag present: must NOT be flagged ===
preg_match('/foo/u', $subject, $matches);
preg_match('/foo/iu', $subject, $matches);
preg_match('/foo/ui', $subject, $matches);
preg_match('/foo/uir', $subject, $matches);
preg_match('#foo#u', $subject);
preg_match("/foo/u", $subject);
preg_match('/a\/b/u', $subject);

// === Not a literal pattern, or an incomplete call: must NOT be flagged ===
preg_match($pattern, $subject, $matches);
preg_match(self::PATTERN, $subject);
preg_match('/foo/');
preg_replace_callback_array(['/foo/' => $callback], $subject);
preg_quote('/foo/');
preg_last_error();

// === Bracket-style delimiters are not understood by the sniff (known limitation of RegexLiteralHelper) ===
preg_match('(foo)', $subject);
preg_match('{foo}i', $subject);

// === Not the global function: must NOT be flagged ===
Foo\preg_match('/foo/', $subject);
$variable = 'preg_match';

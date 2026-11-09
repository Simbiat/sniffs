<?php

declare(strict_types=1);

// === u + i, missing r: every checked function ===
preg_filter('/foo/ui', $replacement, $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_grep('/foo/ui', $array); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_match('/foo/ui', $subject, $matches); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_match_all('/foo/ui', $subject, $matches); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_replace('/foo/ui', $replacement, $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_replace_callback('/foo/ui', $callback, $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_split('/foo/ui', $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag

// === u + i, missing r, in different orders and with other flags ===
preg_match('/foo/iu', $subject, $matches); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_match('/foo/uim', $subject, $matches); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_match('/foo/muis', $subject, $matches); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag

// === Other delimiters and quote styles ===
preg_match('#foo#ui', $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_match("/foo/ui", $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag
preg_match('/a\/b/ui', $subject); // @expect Simbiat.Regex.MissingCaselessRestrictFlag.MissingCaselessRestrictFlag

// === Already has r: must NOT be flagged ===
preg_match('/foo/uir', $subject, $matches);
preg_match('/foo/uri', $subject, $matches);
preg_match('/foo/ruim', $subject, $matches);

// === Missing u or i: this sniff stays silent (MissingUnicodeFlag handles the first case) ===
preg_match('/foo/', $subject, $matches);
preg_match('/foo/i', $subject, $matches);
preg_match('/foo/u', $subject, $matches);
preg_match('/foo/um', $subject, $matches);

// === Not a literal pattern, or an incomplete call: must NOT be flagged ===
preg_match($pattern, $subject, $matches);
preg_match('/foo/ui');
preg_replace_callback_array(['/foo/ui' => $callback], $subject);

// === Not the global function: must NOT be flagged ===
Foo\preg_match('/foo/ui', $subject);

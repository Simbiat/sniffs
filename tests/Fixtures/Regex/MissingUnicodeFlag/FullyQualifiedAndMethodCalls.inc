<?php

declare(strict_types=1);

// === Fully-qualified call: flagged, same as the unqualified one ===
\preg_match('/foo/', $subject, $matches); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag
\preg_replace('/foo/i', $replacement, $subject); // @expect Simbiat.Regex.MissingUnicodeFlag.MissingUnicodeFlag

// === Method / static calls named like a preg_* function: ignored ===
$object->preg_match('/foo/', $subject);
Foo::preg_match('/foo/', $subject);

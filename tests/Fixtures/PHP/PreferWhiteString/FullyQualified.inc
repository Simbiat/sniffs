<?php

declare(strict_types=1);

// === Fully-qualified call: flagged, same as the unqualified one ===
$a = \preg_match('/^\s*$/u', $key) === 1; // @expect Simbiat.PHP.PreferWhiteString.PreferWhiteString

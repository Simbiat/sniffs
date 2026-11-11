<?php

declare(strict_types=1);

// === The fix would remove a line break, and that would change the number of lines of the string ===
// === The sniff reports the problem, but the fixer leaves the string alone (the .fixed file is the same as this file) ===
// @expect-next Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT NOW(
)';

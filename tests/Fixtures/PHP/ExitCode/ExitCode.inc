<?php

declare(strict_types=1);

// === Bare exit/die, no parentheses ===
exit; // @expect Simbiat.PHP.ExitCode.MissingCode
die; // @expect Simbiat.PHP.ExitCode.MissingCode
EXIT; // @expect Simbiat.PHP.ExitCode.MissingCode

if ($something === true) {
    exit; // @expect Simbiat.PHP.ExitCode.MissingCode
}

// === Empty parentheses ===
exit(); // @expect Simbiat.PHP.ExitCode.MissingCode
die(); // @expect Simbiat.PHP.ExitCode.MissingCode
exit( ); // @expect Simbiat.PHP.ExitCode.MissingCode

// === Exit code present: must NOT be flagged ===
exit(1);
die(0);
exit('Something went wrong');
exit($code);
exit(\EXIT_FAILURE);

// === Anything inside the parentheses counts as an argument, even a comment ===
exit(/* intentionally empty */);

// === Not exit/die at all ===
$object->exit();
$value = 'exit';

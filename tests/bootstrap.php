<?php

declare(strict_types=1);

// Used only when the tests of this package run alone (`phpunit` in the package folder).
// When a parent project runs them, its own bootstrap and autoloader are used, and this file is not needed.

$autoload = \dirname(__DIR__).'/vendor/autoload.php';
if (!\is_file($autoload)) {
    \fwrite(\STDERR, "Dependencies are missing. Run \"composer install\" first.\n");

    exit(1);
}

require $autoload;

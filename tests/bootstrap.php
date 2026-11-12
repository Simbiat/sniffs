<?php

declare(strict_types=1);

use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

// If an autoloader is already registered (parent project, or PHPUnit started from vendor/bin),
// it already knows the test classes. Otherwise, the package runs alone and needs its own vendor folder.
if (!\class_exists(PhpcsFactory::class)) {
    $autoload = \dirname(__DIR__).'/vendor/autoload.php';
    if (!\is_file($autoload)) {
        \fwrite(\STDERR, "Cannot load test classes. Run \"composer install\" in the package folder, or run the tests from the parent project.\n");

        exit(1);
    }

    require $autoload;
}

PhpcsFactory::loadPhpcs();

<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Support;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Ruleset;

/**
 * A PHPCS configuration and its ruleset. PHPCS needs both to check a file.
 */
final readonly class Environment
{
    public function __construct(
        public Config  $config,
        public Ruleset $ruleset,
    ) {}
}

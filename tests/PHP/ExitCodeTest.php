<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\PHP;

use PHPUnit\Framework\Attributes\CoversClass;
use Simbiat\Sniffs\PHP\ExitCodeSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;

#[CoversClass(ExitCodeSniff::class)]
final class ExitCodeTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.PHP.ExitCode';
    }

    protected static function codes(): array
    {
        return [
            'MissingCode' => true,
        ];
    }
}

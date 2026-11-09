<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\SQL;

use PHPUnit\Framework\Attributes\CoversClass;
use Simbiat\Sniffs\SQL\DateTimeFunctionsSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;

/**
 * The sniff property `precision` is set per fixture, with a `// @property precision=N` directive.
 */
#[CoversClass(DateTimeFunctionsSniff::class)]
final class DateTimeFunctionsTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.SQL.DateTimeFunctions';
    }

    protected static function codes(): array
    {
        return [
            'PreferCurrent' => true,
            'MissingPrecision' => true,
        ];
    }
}

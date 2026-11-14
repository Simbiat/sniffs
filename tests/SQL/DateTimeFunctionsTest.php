<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\SQL;

use PHPUnit\Framework\Attributes\CoversClass;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;
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

    /**
     * The messages name the function in upper case, and keep the written case of the `UTC_` prefix.
     *
     * @throws \JsonException
     */
    public function testMessagesNameTheFunctions(): void
    {
        $code = <<<'PHP'
            <?php
            $a = 'SELECT UTC_TIMESTAMP() FROM t';
            $b = 'select utc_time from t';
            $c = 'SELECT now() FROM t';
            $d = 'SELECT CURTIME() FROM t';
            PHP;
        $messages = self::messages(PhpcsFactory::checkString($code, PhpcsFactory::forSniff(self::sniffCode())));

        $texts = static function (array $line): array {
            $result = \array_column($line, 'message');
            \sort($result);

            return $result;
        };

        self::assertSame(
            [
                'UTC_TIMESTAMP should explicitly state (0) fractional-second precision.',
                'Use CURRENT_TIMESTAMP instead of UTC_TIMESTAMP.',
            ],
            $texts($messages[2]),
        );
        self::assertSame(
            [
                'UTC_TIME should explicitly state (0) fractional-second precision.',
                'Use CURRENT_TIME instead of utc_TIME.',
            ],
            $texts($messages[3]),
        );
        self::assertSame(['NOW should explicitly state (0) fractional-second precision.'], $texts($messages[4]));
        self::assertSame(['CURTIME should explicitly state (0) fractional-second precision.'], $texts($messages[5]));
    }
}

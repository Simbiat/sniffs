<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\Sniffs\Helpers\RegexLiteralHelper;

#[CoversClass(RegexLiteralHelper::class)]
final class RegexLiteralHelperTest extends TestCase
{
    /**
     * Input is the raw token content, quotes included.
     *
     * @return array<string, array{string, string|null}>
     */
    public static function parseProvider(): array
    {
        return [
            'no flags' => ["'/foo/'", ''],
            'one flag' => ["'/foo/u'", 'u'],
            'several flags' => ["'/foo/imsu'", 'imsu'],
            'double quotes' => ['"/foo/iu"', 'iu'],
            'hash delimiter' => ["'#foo#i'", 'i'],
            'tilde delimiter' => ["'~foo~u'", 'u'],
            'escaped delimiter in the body' => ["'/a\\/b/i'", 'i'],
            'delimiter appears several times' => ["'/a/b/c/u'", 'u'],
            'Unicode in the body' => ["'/пр/u'", 'u'],
            'empty string' => ["''", null],
            'one character in the quotes' => ["'/'", null],
            'no closing delimiter' => ["'/foo'", null],
            'not a quoted string' => ['/foo/', null],
            'only one character' => ["'", null],
            'empty input' => ['', null],
            // Known limitation: bracket-style delimiters need a different closing character.
            'parentheses delimiters' => ["'(foo)i'", null],
            'braces delimiters' => ["'{foo}u'", null],
        ];
    }

    #[DataProvider('parseProvider')]
    public function testParse(string $tokenContent, ?string $expectedFlags): void
    {
        $parsed = RegexLiteralHelper::parse($tokenContent);

        if ($expectedFlags === null) {
            self::assertNull($parsed);

            return;
        }

        self::assertNotNull($parsed);
        self::assertSame(['flags' => $expectedFlags], $parsed);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function appendFlagProvider(): array
    {
        return [
            'no flags yet' => ["'/foo/'", 'u', "'/foo/u'"],
            'after other flags' => ["'/foo/i'", 'u', "'/foo/iu'"],
            'double quotes' => ['"/foo/ui"', 'r', '"/foo/uir"'],
            'hash delimiter' => ["'#foo#'", 'u', "'#foo#u'"],
            'Unicode in the body' => ["'/пр/'", 'u', "'/пр/u'"],
        ];
    }

    #[DataProvider('appendFlagProvider')]
    public function testAppendFlag(string $tokenContent, string $flag, string $expected): void
    {
        self::assertSame($expected, RegexLiteralHelper::appendFlag($tokenContent, $flag));
    }
}

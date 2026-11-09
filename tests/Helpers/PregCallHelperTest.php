<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Helpers\PregCallHelper;
use Simbiat\Sniffs\Helpers\RegexLiteralHelper;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

#[CoversClass(PregCallHelper::class)]
#[UsesClass(CallHelper::class)]
#[UsesClass(RegexLiteralHelper::class)]
final class PregCallHelperTest extends TestCase
{
    /**
     * Every preg_* function of PHP takes a pattern, except these three.
     * If PHP adds a new one, this test fails, and tells you to add it to the list.
     */
    public function testFunctionListHasEveryPatternFunction(): void
    {
        $expected = \array_values(\array_diff(
            \array_keys(new \ReflectionExtension('pcre')->getFunctions()),
            ['preg_last_error', 'preg_last_error_msg', 'preg_quote'],
        ));
        $actual = PregCallHelper::FUNCTIONS;

        \sort($expected);
        \sort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, array{string, string, string}|null}> Code => [function, token content, flags]
     */
    public static function literalPatternProvider(): array
    {
        return [
            'single quotes with flags' => ["preg_match('/foo/iu', \$s)", ['preg_match', "'/foo/iu'", 'iu']],
            'no flags' => ["preg_split('#a#', \$s)", ['preg_split', "'#a#'", '']],
            'upper case name' => ["PREG_REPLACE('/a/', 'b', \$s)", ['preg_replace', "'/a/'", '']],
            'fully-qualified name' => ["\\preg_match('/a/u', \$s)", ['preg_match', "'/a/u'", 'u']],
            'method call' => ["\$o->preg_match('/a/', \$s)", null],
            'static call' => ["Foo::preg_match('/a/', \$s)", null],
            'variable pattern' => ['preg_match($pattern, $s)', null],
            'no second argument' => ["preg_match('/a/')", null],
            'delimiters that are not understood' => ["preg_match('(a)i', \$s)", null],
            'not a preg function' => ["strlen('/a/', \$s)", null],
            'preg function without a pattern' => ["preg_quote('/a/', '/')", null],
        ];
    }

    /**
     * @param array{string, string, string}|null $expected
     */
    #[DataProvider('literalPatternProvider')]
    public function testFindLiteralPattern(string $call, ?array $expected): void
    {
        $file = PhpcsFactory::parseString('<?php '.$call.';');
        $name = $file->findNext([\T_STRING, \T_NAME_FULLY_QUALIFIED], 0);
        self::assertNotFalse($name);

        $pattern = PregCallHelper::findLiteralPattern($file, $name);

        if ($expected === null) {
            self::assertNull($pattern);

            return;
        }

        self::assertNotNull($pattern);
        self::assertSame($expected, [$pattern['function'], $pattern['content'], $pattern['flags']]);
        self::assertSame($pattern['content'], $file->getTokens()[$pattern['pointer']]['content']);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function patternProvider(): array
    {
        return [
            'single quotes' => ["preg_match('/foo/', \$s)", "'/foo/'"],
            'double quotes' => ['preg_match("/foo/i", $s)', '"/foo/i"'],
            'space before the parenthesis' => ["preg_match ('/foo/', \$s)", "'/foo/'"],
            'spaces and line breaks' => ["preg_match(\n    '/foo/',\n    \$s\n)", "'/foo/'"],
            'more arguments' => ["preg_match('/foo/', \$s, \$matches, \\PREG_OFFSET_CAPTURE)", "'/foo/'"],
            'trailing comma only' => ["preg_match('/foo/',)", "'/foo/'"],
            'variable as the pattern' => ['preg_match($pattern, $s)', null],
            'constant as the pattern' => ['preg_match(self::PATTERN, $s)', null],
            'concatenated pattern starts with a literal' => ["preg_match('/foo/' . \$suffix, \$s)", "'/foo/'"],
            'only the pattern, no second argument' => ["preg_match('/foo/')", null],
            'array as the first argument' => ["preg_replace_callback_array(['/foo/' => \$c], \$s)", null],
            'no parenthesis' => ['preg_match;', null],
            'empty call' => ['preg_match()', null],
        ];
    }

    #[DataProvider('patternProvider')]
    public function testFindPatternToken(string $call, ?string $expectedContent): void
    {
        $file = PhpcsFactory::parseString('<?php '.$call.';');
        $tokens = $file->getTokens();

        $name = $file->findNext(\T_STRING, 0);
        self::assertNotFalse($name);

        $pointer = PregCallHelper::findPatternToken($file, $name);

        if ($expectedContent === null) {
            self::assertNull($pointer);

            return;
        }

        self::assertNotNull($pointer);
        self::assertSame($expectedContent, $tokens[$pointer]['content']);
    }
}

<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Helpers;

use PHP_CodeSniffer\Files\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

#[CoversClass(CallHelper::class)]
final class CallHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Load PHPCS and its token constants before the helper classes are compiled (see PhpcsFactory::loadPhpcs()).
        PhpcsFactory::loadPhpcs();
    }

    /**
     * @return array<string, array{list<string>, bool}>
     */
    public static function namedEncodingProvider(): array
    {
        return [
            'no arguments' => [[], false],
            'positional only' => [['$str', ' 0', ' 5'], false],
            'named encoding' => [['$str', " encoding: 'UTF-8'"], true],
            'space before the colon' => [['$str', " encoding : 'UTF-8'"], true],
            'leading line break' => [['$str', "\n    encoding: 'UTF-8'"], true],
            'encoding is the first argument' => [["encoding: 'UTF-8'"], true],
            'other named argument' => [['$str', ' length: 5'], false],
            'name only starts with "encoding"' => [['$str', " encoding_name: 'UTF-8'"], false],
            'variable named $encoding' => [['$str', ' $encoding'], false],
            'string "encoding:"' => [['$str', " 'encoding: x'"], false],
            'array key "encoding"' => [['$str', " ['encoding' => 'UTF-8']"], false],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('namedEncodingProvider')]
    public function testHasNamedEncoding(array $arguments, bool $expected): void
    {
        self::assertSame($expected, CallHelper::hasNamedEncoding($arguments));
    }

    /**
     * Each case: code, the exact token content to inspect, expected result.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function functionCallProvider(): array
    {
        return [
            'plain call' => ['strlen($a);', 'strlen', true],
            'call after return' => ['return strlen($a);', 'strlen', true],
            'call in an expression' => ['$x = 1 + strlen($a);', 'strlen', true],
            'fully-qualified call' => ['\strlen($a);', '\strlen', true],
            'method call' => ['$o->strlen($a);', 'strlen', false],
            'method call, line break before ->' => ["\$o\n    ->strlen(\$a);", 'strlen', false],
            'nullsafe method call' => ['$o?->strlen($a);', 'strlen', false],
            'static call' => ['Foo::strlen($a);', 'strlen', false],
            'function definition' => ['function strlen($a) {}', 'strlen', false],
            'method definition' => ['class Foo { public function strlen($a) {} }', 'strlen', false],
        ];
    }

    #[DataProvider('functionCallProvider')]
    public function testIsFunctionCall(string $code, string $tokenContent, bool $expected): void
    {
        $file = PhpcsFactory::parseString('<?php '.$code);

        self::assertSame($expected, CallHelper::isFunctionCall($file, self::tokenPointer($file, $tokenContent)));
    }

    public function testIsFunctionCallOnTheFirstToken(): void
    {
        // Nothing comes before the first token, so it cannot be a method call or a definition.
        self::assertTrue(CallHelper::isFunctionCall(PhpcsFactory::parseString('strlen($a);'), 0));
    }

    /**
     * Each case: code, the exact token content of the function name, accepted functions, expected name (null = no match).
     *
     * @return array<string, array{string, string, array<int|string, mixed>, string|null}>
     */
    public static function findCallProvider(): array
    {
        return [
            'list of names' => ['strlen($a);', 'strlen', ['strlen', 'trim'], 'strlen'],
            'array keyed by name' => ['strlen($a);', 'strlen', ['strlen' => 1, 'trim' => 2], 'strlen'],
            'upper case name is lowered' => ['STRLEN($a);', 'STRLEN', ['strlen'], 'strlen'],
            'fully-qualified name loses the backslash' => ['\strlen($a);', '\strlen', ['strlen'], 'strlen'],
            'space before the parenthesis' => ['strlen ($a);', 'strlen', ['strlen'], 'strlen'],
            'line break before the parenthesis' => ["strlen\n(\$a);", 'strlen', ['strlen'], 'strlen'],
            'function is not in the list' => ['strlen($a);', 'strlen', ['trim'], null],
            'empty list' => ['strlen($a);', 'strlen', [], null],
            'list does not hold the key form' => ['strlen($a);', 'strlen', ['trim' => 'strlen'], null],
            'method call' => ['$o->strlen($a);', 'strlen', ['strlen'], null],
            'static call' => ['Foo::strlen($a);', 'strlen', ['strlen'], null],
            'function definition' => ['function strlen($a) {}', 'strlen', ['strlen'], null],
            'no parentheses' => ['$x = strlen;', 'strlen', ['strlen'], null],
            'the name is the last token' => ['strlen', 'strlen', ['strlen'], null],
            'the parenthesis is never closed' => ['strlen($a', 'strlen', ['strlen'], null],
            'a comment between the name and the parenthesis' => ['strlen /* c */ ($a);', 'strlen', ['strlen'], null],
        ];
    }

    /**
     * @param array<int|string, mixed> $functions
     */
    #[DataProvider('findCallProvider')]
    public function testFindCall(string $code, string $tokenContent, array $functions, ?string $expectedName): void
    {
        $file = PhpcsFactory::parseString('<?php '.$code);
        $call = CallHelper::findCall($file, self::tokenPointer($file, $tokenContent), $functions);

        if ($expectedName === null) {
            self::assertNull($call);

            return;
        }

        self::assertNotNull($call);
        self::assertSame($expectedName, $call['name']);

        $tokens = $file->getTokens();
        self::assertSame(\T_OPEN_PARENTHESIS, $tokens[$call['open']]['code']);
        self::assertSame(\T_CLOSE_PARENTHESIS, $tokens[$call['close']]['code']);
        self::assertSame($call['close'], $tokens[$call['open']]['parenthesis_closer']);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function trailingCommaProvider(): array
    {
        return [
            'no arguments' => ['f()', false],
            'no trailing comma' => ['f($a, $b)', false],
            'trailing comma' => ['f($a, $b,)', true],
            'trailing comma and a space' => ['f($a, $b, )', true],
            'trailing comma and line breaks' => ["f(\n    \$a,\n    \$b,\n)", true],
            'trailing comma and a comment' => ['f($a, $b, /* last */)', true],
            'comma only inside a comment' => ['f($a, $b /* last, */)', false],
            'comma only inside a nested call' => ['f($a, g($b,))', false],
            'comma inside a short array' => ['f([1, 2])', false],
            'trailing comma after a short array' => ['f([1, 2],)', true],
        ];
    }

    #[DataProvider('trailingCommaProvider')]
    public function testHasTrailingComma(string $call, bool $expected): void
    {
        $file = PhpcsFactory::parseString('<?php '.$call.';');
        $open = $file->findNext(\T_OPEN_PARENTHESIS, 0);
        self::assertNotFalse($open);

        /** @noinspection OffsetOperationsInspection False-positive, will fail if `false` */
        self::assertSame(
            $expected,
            CallHelper::hasTrailingComma($file, $open, $file->getTokens()[$open]['parenthesis_closer']),
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function splitArgumentsProvider(): array
    {
        return [
            'no arguments' => ['f()', []],
            'only whitespace' => ['f(  )', []],
            'one argument' => ['f($a)', ['$a']],
            'two arguments, whitespace is kept' => ['f($a, $b)', ['$a', ' $b']],
            'nested call' => ['f(g(1, 2), $b)', ['g(1, 2)', ' $b']],
            'long array' => ['f(array(1, 2), $b)', ['array(1, 2)', ' $b']],
            'short array' => ['f([1, 2, 3], $b)', ['[1, 2, 3]', ' $b']],
            'short array with keys' => ["f(['a' => 1, 'b' => 2])", ["['a' => 1, 'b' => 2]"]],
            'nested short arrays' => ['f([[1, 2], [3, 4]], $b)', ['[[1, 2], [3, 4]]', ' $b']],
            'array access with a call' => ['f($a[g(1, 2)], $b)', ['$a[g(1, 2)]', ' $b']],
            'string with commas' => ["f('a,b,c', \$b)", ["'a,b,c'", ' $b']],
            'closure with commas inside' => ['f(function ($x, $y) { return [$x, $y]; }, $b)', ['function ($x, $y) { return [$x, $y]; }', ' $b']],
            'arrow function' => ['f(fn($x, $y) => $x + $y, $b)', ['fn($x, $y) => $x + $y', ' $b']],
            'match expression' => ['f(match ($x) { 1 => 2, 3 => 4 }, $b)', ['match ($x) { 1 => 2, 3 => 4 }', ' $b']],
            'named arguments' => ["f(\$a, encoding: 'UTF-8')", ['$a', " encoding: 'UTF-8'"]],
            'trailing comma is not an argument' => ['f($a, $b,)', ['$a', ' $b']],
            'trailing comma and line breaks' => ["f(\n    \$a,\n    \$b,\n)", ["\n    \$a", "\n    \$b"]],
            'an empty slot is kept' => ['f($a, , $b)', ['$a', ' ', ' $b']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('splitArgumentsProvider')]
    public function testSplitArguments(string $call, array $expected): void
    {
        self::assertSame($expected, self::split($call));
    }

    /**
     * @return list<string>
     */
    private static function split(string $call): array
    {
        $file = PhpcsFactory::parseString('<?php '.$call.';');
        $tokens = $file->getTokens();

        $open = $file->findNext(\T_OPEN_PARENTHESIS, 0);
        self::assertNotFalse($open);

        /** @noinspection OffsetOperationsInspection False-positive, will fail if `false` */
        return CallHelper::splitArguments($file, $open, $tokens[$open]['parenthesis_closer']);
    }

    private static function tokenPointer(File $file, string $content): int
    {
        foreach ($file->getTokens() as $pointer => $token) {
            if ($token['content'] === $content) {
                return $pointer;
            }
        }

        self::fail('Token "'.$content.'" not found in the test code.');
    }
}

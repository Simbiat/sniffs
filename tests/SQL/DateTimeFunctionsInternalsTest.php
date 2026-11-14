<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\SQL;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\Sniffs\SQL\DateTimeFunctionsSniff;

/**
 * Direct tests of the helper methods of the sniff.
 *
 * The fixtures cannot show a mistake in these methods: most of them only decide how many fragments of a string are
 * read, or in how many passes the fixer reaches the final result, and the final result is the same.
 */
#[CoversClass(DateTimeFunctionsSniff::class)]
final class DateTimeFunctionsInternalsTest extends TestCase
{
    /**
     * @param list<array{int, string}> $tokens [code, content] of each token
     *
     * @return array<int, array{code: int, content: string}>
     */
    private static function tokens(array $tokens): array
    {
        $result = [];
        foreach ($tokens as $index => [$code, $content]) {
            $result[$index] = ['code' => $code, 'content' => $content];
        }

        return $result;
    }

    /**
     * @return array<string, array{list<array{int, string}>, int, bool, list<int>}> Tokens, start, is heredoc, expected pointers
     */
    public static function fragmentsProvider(): array
    {
        $string = \T_CONSTANT_ENCAPSED_STRING;
        $heredoc = \T_HEREDOC;
        $other = \T_WHITESPACE;

        return [
            'a complete string is not extended by the next string token' => [[[$string, "'a'"], [$string, "'b'"]], 0, false, [0]],
            'two fragments' => [[[$string, "'a\n"], [$string, "b'"]], 0, false, [0, 1]],
            'three fragments' => [[[$string, "'a\n"], [$string, "b\n"], [$string, "c'"]], 0, false, [0, 1, 2]],
            'stops at the closing fragment' => [[[$string, "'a\n"], [$string, "b'"], [$string, "c'"]], 0, false, [0, 1]],
            'the tokens end before the closing fragment' => [[[$string, "'a\n"]], 0, false, [0]],
            'stops at a token of another type' => [[[$string, "'a\n"], [\T_VARIABLE, '$x']], 0, false, [0]],
            'a token of another string type is not a fragment' => [[[$string, "'a\n"], [\T_HEREDOC, "b'"]], 0, false, [0]],
            'an escaped quote does not close the string' => [[[$string, "'a\\'"], [$string, "b'"]], 0, false, [0, 1]],
            'a first fragment that is only a quote does not close the string' => [[[$string, "'"], [$string, "a'"]], 0, false, [0, 1]],
            'a last fragment that is only a quote closes the string' => [[[$string, "'a\n"], [$string, "'"], [$string, "b'"]], 0, false, [0, 1]],
            'double quotes' => [[[$string, "\"a\n"], [$string, 'b"'], [$string, 'c"']], 0, false, [0, 1]],
            'only the quote of the first fragment closes the string' => [[[$string, "'a\n"], [$string, 'b"'], [$string, "c'"]], 0, false, [0, 1, 2]],
            'the string does not start at the first token' => [[[$other, ' '], [$string, "'a\n"], [$string, "b'"]], 1, false, [1, 2]],
            'heredoc' => [[[$other, 'x'], [$heredoc, "a\n"], [$heredoc, "b\n"], [$heredoc, "c\n"], [$other, 'end']], 1, true, [1, 2, 3]],
            'heredoc up to the last token' => [[[$heredoc, "a\n"], [$heredoc, "b\n"]], 0, true, [0, 1]],
            'heredoc of one line' => [[[$heredoc, "a\n"], [$other, 'x']], 0, true, [0]],
        ];
    }

    /**
     * @param list<array{int, string}> $tokens
     * @param list<int>                $expected
     */
    #[DataProvider('fragmentsProvider')]
    public function testCollectFragments(array $tokens, int $start, bool $isHeredoc, array $expected): void
    {
        self::assertSame($expected, DateTimeFunctionsSniff::collectFragments(self::tokens($tokens), $start, $isHeredoc));
    }

    /**
     * @return array<string, array{string, string, bool, bool}> Content, quote, is first fragment, expected
     */
    public static function endsWithUnescapedQuoteProvider(): array
    {
        return [
            'a complete string' => ["'a'", "'", true, true],
            'an empty string' => ["''", "'", true, true],
            'an escaped quote' => ["'a\\'", "'", true, false],
            'an escaped backslash and a quote' => ["'a\\\\'", "'", true, true],
            'an escaped backslash, an escaped quote' => ["'a\\\\\\'", "'", true, false],
            'only a quote, as the first fragment' => ["'", "'", true, false],
            'only a quote, as another fragment' => ["'", "'", false, true],
            'the end of a fragment' => ["a'", "'", false, true],
            'no quote at the end' => ['a', "'", false, false],
            'another quote at the end' => ['a"', "'", false, false],
            'nothing' => ['', "'", false, false],
            'an escaped quote, as another fragment' => ["\\'", "'", false, false],
            'one backslash before the quote' => ["a\\'", "'", false, false],
            'two backslashes, down to the first character' => ["\\\\'", "'", false, true],
            'double quotes' => ['"a\\\\"', '"', true, true],
            'multibyte characters before the end' => ["'я'", "'", true, true],
            'multibyte characters, escaped quote' => ["'я\\'", "'", true, false],
            'a backslash that is not next to the quote, after multibyte characters' => ["я\\a'", "'", false, true],
        ];
    }

    #[DataProvider('endsWithUnescapedQuoteProvider')]
    public function testEndsWithUnescapedQuote(string $content, string $quote, bool $isFirstFragment, bool $expected): void
    {
        self::assertSame($expected, DateTimeFunctionsSniff::endsWithUnescapedQuote($content, $quote, $isFirstFragment));
    }

    /**
     * @return array<string, array{list<array{int, string}>, int, bool, bool}> Tokens, pointer, is heredoc, expected
     */
    public static function fragmentStartProvider(): array
    {
        $heredoc = \T_HEREDOC;
        $other = \T_WHITESPACE;
        $string = \T_CONSTANT_ENCAPSED_STRING;

        return [
            'the first line of a heredoc' => [[[$other, ' '], [$heredoc, 'a']], 1, true, true],
            'the second line of a heredoc' => [[[$other, ' '], [$heredoc, 'a'], [$heredoc, 'b']], 2, true, false],
            'the third line of a heredoc' => [[[$other, ' '], [$heredoc, 'a'], [$heredoc, 'b'], [$heredoc, 'c']], 3, true, false],
            'a heredoc at the very start' => [[[$heredoc, 'a']], 0, true, true],
            'a single-quoted string' => [[[$string, "'a'"]], 0, false, true],
            'a double-quoted string' => [[[$string, '"a"']], 0, false, true],
            'a continuation fragment' => [[[$string, "a'"]], 0, false, false],
            'an empty fragment' => [[[$string, '']], 0, false, false],
        ];
    }

    /**
     * @param list<array{int, string}> $tokens
     */
    #[DataProvider('fragmentStartProvider')]
    public function testIsFragmentStart(array $tokens, int $pointer, bool $isHeredoc, bool $expected): void
    {
        self::assertSame($expected, DateTimeFunctionsSniff::isFragmentStart(self::tokens($tokens), $pointer, $isHeredoc));
    }

    /**
     * @return array<string, array{list<array<string, int|string>>, list<array<string, int|string>>}> Edits, expected result
     */
    public static function mergeProvider(): array
    {
        $utc = ['type' => 'utc', 'offset' => 5, 'length' => 13, 'replacement' => 'CURRENT_TIMESTAMP'];
        $precision = ['type' => 'precision', 'offset' => 5, 'length' => 15, 'replacement' => 'UTC_TIMESTAMP(6)', 'target' => '6'];
        $second = ['type' => 'precision', 'offset' => 20, 'length' => 5, 'replacement' => 'NOW(6)', 'target' => '6'];
        $third = ['type' => 'utc', 'offset' => 30, 'length' => 8, 'replacement' => 'CURRENT_DATE'];
        $merged = ['length' => 15, 'offset' => 5, 'replacement' => 'CURRENT_TIMESTAMP(6)', 'type' => 'merged'];
        $long_utc = ['type' => 'utc', 'offset' => 5, 'length' => 20, 'replacement' => 'CURRENT_TIMESTAMP'];

        return [
            'no edits' => [[], []],
            'one edit stays as it is' => [[$utc], [$utc]],
            'edits at different offsets stay as they are' => [[$utc, $second], [$utc, $second]],
            'all of several edits at different offsets stay' => [[$utc, $second, $third], [$utc, $second, $third]],
            'edits at one offset are merged' => [[$utc, $precision], [$merged]],
            'the order of the edits does not matter' => [[$precision, $utc], [$merged]],
            'the longer length is used' => [[$precision, $long_utc], [['length' => 20, 'offset' => 5, 'replacement' => 'CURRENT_TIMESTAMP(6)', 'type' => 'merged']]],
            'merged and separate edits' => [[$second, $precision, $utc], [$second, $merged]],
        ];
    }

    /**
     * @param list<array{type: string, offset: int, length: int, replacement: string, target?: string}> $edits
     * @param list<array<string, int|string>>                                                           $expected
     */
    #[DataProvider('mergeProvider')]
    public function testMergeOverlappingEdits(array $edits, array $expected): void
    {
        self::assertSame($expected, DateTimeFunctionsSniff::mergeOverlappingEdits($edits));
    }
}

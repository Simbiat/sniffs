<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests;

use PHP_CodeSniffer\Files\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\Sniffs\Tests\Support\Environment;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

/**
 * Base class for the tests of one sniff.
 *
 * A test class declares the sniff and its codes. The fixtures are `.inc` files in
 * `tests/Fixtures/<Category>/<Sniff>/`. This class runs the sniff on each fixture.
 *
 * Fixture directives (all in `//` comments):
 *
 *  - `// @expect Code.One Code.Two`       The line must give these results (full source codes).
 *  - `// @expect-next Code.One`           The NEXT line must give these results. Use this for
 *                                         multi-line strings and heredocs, where a comment cannot
 *                                         go on the line itself.
 *  - `// @expect+2 Code.One`              The same, but N lines below (here: 2). `@expect-next` is `@expect+1`.
 *  - `// @property name=value`            Set a sniff property for the whole fixture.
 *  - `// @known-issue Text`               The fixture describes DESIRED behavior that the sniff does not
 *                                         have yet (or a wrong fixer output). `@expect` and `.fixed` hold the
 *                                         CORRECT result. While the sniff is wrong, the tests are reported as
 *                                         "incomplete", not as "failed". When the sniff becomes correct, the
 *                                         tests fail, and tell you to remove the directive.
 *
 * A line without `@expect` must give NO result. This is how fixtures prove "must not be flagged".
 *
 * Fixer: if `Fixture.inc.fixed` exists, the fixer output must be equal to it. If it does not exist,
 * the fixer must not change the fixture.
 */
abstract class SniffTestCase extends TestCase
{
    /**
     * The sniff code, for example `Simbiat.Strings.MultibyteEncoding`.
     */
    abstract protected static function sniffCode(): string;

    /**
     * Every code (last part of the source) that the sniff can give, mapped to "can the fixer fix it?".
     *
     * @return array<string, bool>
     */
    abstract protected static function codes(): array;

    /**
     * @return array<string, array{string}> Fixture name => [absolute path]
     */
    public static function fixtures(): array
    {
        $files = \glob(self::fixtureDirectory().'/*.inc');
        $result = [];
        foreach ($files === false ? [] : $files as $path) {
            $result[\basename($path)] = [$path];
        }

        return $result;
    }

    /**
     * @throws \JsonException
     */
    #[DataProvider('fixtures')]
    public function testSniffGivesExactlyTheExpectedResults(string $fixture): void
    {
        $expected = self::expectedResults($fixture);
        $actual = self::actualResults($this->check($fixture));

        $known_issue = self::knownIssue($fixture);
        if ($known_issue !== null) {
            if ($expected !== $actual) {
                self::markTestIncomplete('Known issue: '.$known_issue);
            }

            // The report is right. The issue is fixed only if the fixer is right too.
            [$expected_fixed, $actual_fixed] = $this->fixerResult($fixture);
            self::assertNotSame(
                $expected_fixed,
                $actual_fixed,
                'The known issue in '.\basename($fixture).' seems to be fixed. Remove the @known-issue directive.',
            );
            self::markTestIncomplete('Known issue: '.$known_issue);
        }

        self::assertSame(
            $expected,
            $actual,
            'Results of '.\basename($fixture).' are different from the @expect directives (line => sources).',
        );
    }

    /**
     * @throws \JsonException
     */
    #[DataProvider('fixtures')]
    public function testFixableFlagMatchesTheDeclaredCodes(string $fixture): void
    {
        $file = $this->check($fixture);
        $codes = static::codes();

        foreach (self::messages($file) as $line => $messages) {
            foreach ($messages as $message) {
                $code = \substr($message['source'], \strlen(static::sniffCode()) + 1);
                self::assertArrayHasKey($code, $codes, 'Line '.$line.' gave an undeclared code: '.$message['source']);
                self::assertSame(
                    $codes[$code],
                    $message['fixable'],
                    'Line '.$line.': "fixable" flag is wrong for '.$message['source'],
                );
            }
        }
    }

    #[DataProvider('fixtures')]
    public function testFixtureIsValidPhp(string $fixture): void
    {
        self::assertValidPhp(self::read($fixture), \basename($fixture));
    }

    /**
     * @throws \JsonException
     */
    #[DataProvider('fixtures')]
    public function testFixerOutput(string $fixture): void
    {
        [$expected, $actual] = $this->fixerResult($fixture);

        $known_issue = self::knownIssue($fixture);
        if ($known_issue !== null && $expected !== $actual) {
            self::markTestIncomplete('Known issue: '.$known_issue);
        }

        self::assertSame($expected, $actual, 'Fixer output is different from the expected file.');

        // The fixed code must be stable: the sniff must not want to fix it again.
        // This applies only when the fixer changed the file (a fixture can show a problem that the fixer leaves alone on purpose).
        if ($expected !== self::read($fixture)) {
            $again = PhpcsFactory::checkString($expected, $this->environment($fixture));

            self::assertSame(0, $again->getFixableCount(), 'The fixed file still has fixable results: the fix is not stable.');
        }
    }

    /**
     * @throws \Throwable
     * @throws \JsonException
     */
    #[DataProvider('fixtures')]
    public function testFixerOutputIsValidPhp(string $fixture): void
    {
        $file = $this->check($fixture);
        $file->fixer->fixFile();

        $known_issue = self::knownIssue($fixture);
        try {
            self::assertValidPhp($file->fixer->getContents(), 'The fixer output of '.\basename($fixture));
        } catch (\Throwable $exception) {
            if ($known_issue !== null) {
                self::markTestIncomplete('Known issue: '.$known_issue);
            }

            throw $exception;
        }
    }

    public function testEveryDeclaredCodeIsCoveredByAFixture(): void
    {
        $seen = [];
        foreach (self::fixtures() as [$fixture]) {
            foreach (self::expectedResults($fixture) as $sources) {
                foreach ($sources as $source) {
                    $seen[$source] = true;
                }
            }
        }

        foreach (\array_keys(static::codes()) as $code) {
            $index = static::sniffCode().'.'.$code;
            self::assertTrue(
                isset($seen[$index]),
                'No fixture expects the code '.$index.'.',
            );
        }
    }

    /**
     * Run the fixer on a fixture.
     *
     * @return array{string, string} Expected content, actual content
     * @throws \JsonException
     */
    private function fixerResult(string $fixture): array
    {
        $file = $this->check($fixture);
        $had_fixable = $file->getFixableCount() > 0;
        $fixed_path = $fixture.'.fixed';

        if ($had_fixable && !\is_file($fixed_path)) {
            self::fail(\basename($fixture).' has fixable results, but '.\basename($fixed_path).' does not exist.');
        }

        $converged = $file->fixer->fixFile();
        if ($had_fixable && self::knownIssue($fixture) === null) {
            self::assertTrue($converged, 'The fixer did not finish for '.\basename($fixture).' (conflict or too many loops).');
        }

        return [
            \is_file($fixed_path) ? self::read($fixed_path) : self::read($fixture),
            $file->fixer->getContents(),
        ];
    }

    /**
     * Run the sniff on a fixture. Use this in the test classes for extra assertions.
     *
     * @throws \JsonException
     */
    protected function check(string $fixture): File
    {
        return PhpcsFactory::checkFile($fixture, $this->environment($fixture));
    }

    /**
     * Results of a file, grouped by line.
     *
     * @return array<int, list<array{message: string, source: string, fixable: bool, type: string}>>
     */
    protected static function messages(File $file): array
    {
        $result = [];
        foreach (['error' => $file->getErrors(), 'warning' => $file->getWarnings()] as $type => $by_line) {
            foreach ($by_line as $line => $by_column) {
                foreach ($by_column as $messages) {
                    foreach ($messages as $message) {
                        $result[$line][] = [
                            'message' => $message['message'],
                            'source' => $message['source'],
                            'fixable' => $message['fixable'],
                            'type' => $type,
                        ];
                    }
                }
            }
        }

        \ksort($result);

        return $result;
    }

    protected static function fixtureDirectory(): string
    {
        $parts = \explode('.', static::sniffCode());

        return PhpcsFactory::root().'/tests/Fixtures/'.$parts[1].'/'.$parts[2];
    }

    /**
     * @throws \JsonException
     */
    private function environment(string $fixture): Environment
    {
        return PhpcsFactory::forSniff(static::sniffCode(), self::properties($fixture));
    }

    /**
     * @return array<int, list<string>> line => sorted list of sources
     */
    private static function actualResults(File $file): array
    {
        $result = [];
        foreach (self::messages($file) as $line => $messages) {
            $sources = \array_column($messages, 'source');
            \sort($sources);
            $result[$line] = $sources;
        }

        return $result;
    }

    /**
     * @return array<int, list<string>> line => sorted list of sources
     */
    private static function expectedResults(string $fixture): array
    {
        $result = [];
        foreach (self::lines($fixture) as $index => $text) {
            if (\preg_match('~//\s*@expect(-next|\+\d+)?((?:\s+[A-Za-z]+(?:\.[A-Za-z]+){3})+)~', $text, $match) !== 1) {
                continue;
            }

            $offset = match (true) {
                $match[1] === '' => 0,
                $match[1] === '-next' => 1,
                default => (int) \substr($match[1], 1),
            };
            $line = $index + 1 + $offset;
            $sources = \preg_split('/\s+/', \trim($match[2]), -1, \PREG_SPLIT_NO_EMPTY);
            \assert($sources !== false);
            $result[$line] = \array_merge($result[$line] ?? [], $sources);
        }

        foreach ($result as &$sources) {
            \sort($sources);
        }

        unset($sources);
        \ksort($result);

        return $result;
    }

    private static function knownIssue(string $fixture): ?string
    {
        foreach (self::lines($fixture) as $text) {
            if (\preg_match('~^\s*//\s*@known-issue\s+(.+)$~', $text, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private static function assertValidPhp(string $code, string $what): void
    {
        try {
            (void) \token_get_all($code, \TOKEN_PARSE);
        } catch (\ParseError $error) {
            self::fail($what.' is not valid PHP: '.$error->getMessage().' (line '.$error->getLine().')');
        }

        self::assertTrue(true);
    }

    /**
     * @return array<string, string>
     */
    private static function properties(string $fixture): array
    {
        $properties = [];
        foreach (self::lines($fixture) as $text) {
            if (\preg_match('~^\s*//\s*@property\s+(\w+)=(\S+)\s*$~', $text, $match) === 1) {
                $properties[$match[1]] = $match[2];
            }
        }

        return $properties;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $path): array
    {
        $lines = \preg_split('/\R/', self::read($path));

        return $lines === false ? [] : $lines;
    }

    private static function read(string $path): string
    {
        $content = \file_get_contents($path);
        if ($content === false) {
            self::fail('Cannot read '.$path);
        }

        return $content;
    }
}

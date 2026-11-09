<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests;

use Dom\XMLDocument;
use JetBrains\PhpStorm\Pure;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

/**
 * Checks that the pieces of this repository agree with each other:
 * sniff files, `ruleset.xml`, test classes, fixtures and the README.
 * When you add a sniff and forget one of them, a test here fails.
 */
#[CoversNothing]
final class RulesetTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Every sniff class implements a PHPCS interface, so PHPCS must be loadable before we check the classes.
        PhpcsFactory::loadPhpcs();
    }

    /**
     * @return array<string, array{string}> Sniff code => [code]
     */
    public static function sniffs(): array
    {
        $result = [];
        $files = \glob(PhpcsFactory::root().'/Simbiat/Sniffs/*/*Sniff.php');
        foreach ($files === false ? [] : $files as $path) {
            $code = 'Simbiat.'.\basename(\dirname($path)).'.'.\substr(\basename($path), 0, -\strlen('Sniff.php'));
            $result[$code] = [$code];
        }

        return $result;
    }

    /**
     * @return array<string, array{string}> Reference => [reference]
     */
    public static function rulesetReferences(): array
    {
        $result = [];
        foreach (self::loadRuleset()->getElementsByTagName('rule') as $rule) {
            $reference = $rule->getAttribute('ref');
            if ($reference !== '') {
                $result[$reference] = [$reference];
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{string}> Fixture directory => [absolute path]
     */
    #[Pure(true)]
    public static function fixtureDirectories(): array
    {
        $result = [];
        $directories = \glob(PhpcsFactory::root().'/tests/Fixtures/*/*', \GLOB_ONLYDIR);
        foreach ($directories === false ? [] : $directories as $path) {
            $result[\basename(\dirname($path)).'/'.\basename($path)] = [$path];
        }

        return $result;
    }

    /**
     * @return array<string, array{string}> Relative path => [absolute path]
     */
    #[Pure(true)]
    public static function fixedFiles(): array
    {
        $result = [];
        $files = \glob(PhpcsFactory::root().'/tests/Fixtures/*/*/*.inc.fixed');
        foreach ($files === false ? [] : $files as $path) {
            $result[\substr($path, \strlen(PhpcsFactory::root()) + 1)] = [$path];
        }

        return $result;
    }

    public function testThereAreSniffs(): void
    {
        self::assertGreaterThan(0, \count(self::sniffs()), 'No sniff files found: the glob in this test is wrong.');
    }

    public function testRulesetIsWellFormed(): void
    {
        self::assertSame('Simbiat', self::loadRuleset()->documentElement?->getAttribute('name'));
    }

    #[DataProvider('sniffs')]
    public function testSniffIsRegisteredInRuleset(string $code): void
    {
        $references = \array_keys(self::rulesetReferences());
        $registered = \array_any(
            $references,
            static fn(string $reference): bool => $reference === $code || \str_starts_with($reference, $code.'.'),
        );

        self::assertTrue($registered, $code.' is not referenced in Simbiat/ruleset.xml.');
    }

    #[DataProvider('rulesetReferences')]
    public function testRulesetReferenceHasASniffFile(string $reference): void
    {
        $parts = \explode('.', $reference);
        self::assertGreaterThanOrEqual(3, \count($parts), '"'.$reference.'" is not a sniff reference.');

        self::assertArrayHasKey(
            $parts[0].'.'.$parts[1].'.'.$parts[2],
            self::sniffs(),
            'ruleset.xml references '.$reference.', but the sniff file does not exist.',
        );
    }

    #[DataProvider('sniffs')]
    public function testSniffClassIsValid(string $code): void
    {
        $parts = \explode('.', $code);
        $class = 'Simbiat\\Sniffs\\'.$parts[1].'\\'.$parts[2].'Sniff';

        self::assertTrue(\class_exists($class), $code.': class '.$class.' cannot be loaded (wrong namespace or file name?).');
        self::assertContains(Sniff::class, \class_implements($class) ?: [], $class.' must implement the PHPCS Sniff interface.');
    }

    #[DataProvider('sniffs')]
    public function testSniffLoadsFromTheStandard(string $code): void
    {
        self::assertArrayHasKey(
            $code,
            PhpcsFactory::forStandard()->ruleset->sniffCodes,
            'PHPCS did not load '.$code.' from the standard.',
        );
    }

    #[DataProvider('sniffs')]
    public function testSniffHasATestClassWithFixtures(string $code): void
    {
        $parts = \explode('.', $code);
        $test_class = 'Simbiat\\Sniffs\\Tests\\'.$parts[1].'\\'.$parts[2].'Test';

        self::assertTrue(\class_exists($test_class), $code.' has no test class. Expected '.$test_class.'.');
        self::assertTrue(\is_subclass_of($test_class, SniffTestCase::class), $test_class.' must extend SniffTestCase.');

        $fixtures = \glob(PhpcsFactory::root().'/tests/Fixtures/'.$parts[1].'/'.$parts[2].'/*.inc');
        self::assertNotEmpty($fixtures, $code.' has no fixtures in tests/Fixtures/'.$parts[1].'/'.$parts[2].'/.');
    }

    #[DataProvider('sniffs')]
    public function testSniffIsDocumentedInReadme(string $code): void
    {
        $readme = \file_get_contents(PhpcsFactory::root().'/README.md');
        self::assertNotFalse($readme);

        self::assertStringContainsString($code, $readme, $code.' is not mentioned in README.md.');
    }

    #[DataProvider('fixtureDirectories')]
    public function testFixtureDirectoryBelongsToASniff(string $directory): void
    {
        $code = 'Simbiat.'.\basename(\dirname($directory)).'.'.\basename($directory);

        self::assertArrayHasKey($code, self::sniffs(), $directory.' has fixtures, but the sniff '.$code.' does not exist.');
    }

    #[DataProvider('fixedFiles')]
    public function testFixedFileHasItsFixture(string $fixedFile): void
    {
        self::assertFileExists(\substr($fixedFile, 0, -\strlen('.fixed')), 'Orphan file: the .inc for '.$fixedFile.' does not exist.');
    }

    private static function loadRuleset(): XMLDocument
    {
        try {
            return XMLDocument::createFromFile(PhpcsFactory::root().'/Simbiat/ruleset.xml', \LIBXML_NONET);
        } catch (\Throwable $throwable) {
            self::fail('Simbiat/ruleset.xml is not valid XML: '.$throwable->getMessage());
        }
    }
}

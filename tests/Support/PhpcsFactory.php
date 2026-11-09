<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Support;

use Composer\InstalledVersions;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Builds PHPCS objects for the tests.
 *
 * This class does what `phpcs` does at start-up, but it does not run a full CLI process.
 * It registers this repository as an installed standard, so `Simbiat.Category.Sniff`
 * codes resolve in the same way as they do in a project that installs this package.
 */
final class PhpcsFactory
{
    /**
     * Environments are expensive to build, so each one is built one time per process.
     *
     * @var array<string, Environment>
     */
    private static array $environments = [];

    /**
     * Temporary ruleset files that we must delete at the end of the run.
     *
     * @var list<string>
     */
    private static array $temporary_files = [];

    /**
     * Whether shutdown class was registered
     * @var bool
     */
    private static bool $shutdown_registered = false;

    /**
     * Absolute path to the root of this repository (the folder that contains `Simbiat/ruleset.xml`).
     */
    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * An environment with exactly one sniff enabled.
     *
     * This isolates the sniff. Other sniffs in the standard cannot add results to the output.
     *
     * @param string                $sniffCode  For example `Simbiat.Strings.MultibyteEncoding`.
     * @param array<string, string> $properties Sniff properties, for example `['precision' => '6']`.
     *
     * @throws \JsonException
     */
    public static function forSniff(string $sniffCode, array $properties = []): Environment
    {
        \ksort($properties);
        $key = $sniffCode.'|'.\json_encode($properties, \JSON_THROW_ON_ERROR);

        if (!isset(self::$environments[$key])) {
            $xml = '<?xml version="1.0"?>'."\n".'<ruleset name="SimbiatSniffTest">'."\n";
            $xml .= '    <rule ref="'.\htmlspecialchars($sniffCode, \ENT_QUOTES | \ENT_XML1).'">'."\n";
            if ($properties !== []) {
                $xml .= "        <properties>\n";
                foreach ($properties as $name => $value) {
                    $xml .= '            <property name="'.\htmlspecialchars($name, \ENT_QUOTES | \ENT_XML1)
                        .'" value="'.\htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1)."\"/>\n";
                }

                $xml .= "        </properties>\n";
            }

            $xml .= "    </rule>\n</ruleset>\n";

            self::$environments[$key] = self::build(self::writeTemporaryRuleset($xml));
        }

        return self::$environments[$key];
    }

    /**
     * An environment with the real `Simbiat/ruleset.xml`, as a user of this package gets it.
     */
    public static function forStandard(): Environment
    {
        return self::$environments['standard'] ??= self::build(self::root().'/Simbiat/ruleset.xml');
    }

    /**
     * Check a file on disk. Returns the file after processing, so the caller can read results or run the fixer.
     */
    public static function checkFile(string $path, Environment $environment): File
    {
        $file = new LocalFile($path, $environment->ruleset, $environment->config);
        $file->process();

        return $file;
    }

    /**
     * Check a string of code, as if it were a file.
     */
    public static function checkString(string $code, Environment $environment): File
    {
        $file = new DummyFile($code, $environment->ruleset, $environment->config);
        $file->process();

        return $file;
    }

    /**
     * Tokenize a string of code without running any sniff. Used for tests of helper classes.
     *
     * PHPCS needs a ruleset with at least one sniff. By default we use a core PHPCS sniff,
     * so that no sniff of this package is loaded (this keeps code coverage reports exact).
     */
    public static function parseString(string $code, ?Environment $environment = null): File
    {
        $environment ??= self::forSniff('Generic.PHP.DisallowShortOpenTag');
        $file = new DummyFile($code, $environment->ruleset, $environment->config);
        $file->parse();

        return $file;
    }

    /**
     * PHPCS 4 has no Composer autoload section: its own scripts load its classes with `require 'autoload.php'`.
     * Composer (and the IDE) do not know them, so we load that file here, if no other autoloader can find PHPCS.
     */
    public static function loadPhpcs(): void
    {
        if (\class_exists(Config::class)) {
            return;
        }

        $path = \class_exists(InstalledVersions::class) ? InstalledVersions::getInstallPath('squizlabs/php_codesniffer') : null;
        if ($path === null || !\is_file($path.'/autoload.php')) {
            throw new \RuntimeException('Cannot find PHP_CodeSniffer. Install squizlabs/php_codesniffer with Composer.');
        }

        require_once $path.'/autoload.php';
    }

    /**
     * PHPCS expects some constants. Its own CLI scripts define them, but here PHPCS runs inside PHPUnit.
     * The checks let this work in a monorepo, where another bootstrap file may already define them.
     */
    private static function defineConstants(): void
    {
        if (!\defined('PHP_CODESNIFFER_IN_TESTS')) {
            \define('PHP_CODESNIFFER_IN_TESTS', true);
        }

        if (!\defined('PHP_CODESNIFFER_CBF')) {
            \define('PHP_CODESNIFFER_CBF', false);
        }

        if (!\defined('PHP_CODESNIFFER_VERBOSITY')) {
            \define('PHP_CODESNIFFER_VERBOSITY', 0);
        }
    }

    private static function build(string $rulesetPath): Environment
    {
        self::loadPhpcs();
        self::defineConstants();

        $config = new Config(
            [
                '--standard='.$rulesetPath,
                '--runtime-set',
                'installed_paths',
                self::root(),
                '--no-cache',
                '--no-colors',
                '-q',
            ],
            false,
        );

        // Same as `Runner::init()` does: load the token constants.
        (void) new Tokens();

        return new Environment($config, new Ruleset($config));
    }

    private static function writeTemporaryRuleset(string $xml): string
    {
        $path = \sys_get_temp_dir().\DIRECTORY_SEPARATOR.'simbiat-sniffs-'.\getmypid().'-'.\md5($xml).'.xml';
        \file_put_contents($path, $xml);
        self::$temporary_files[] = $path;

        if (!self::$shutdown_registered) {
            self::$shutdown_registered = true;
            \register_shutdown_function(static function (): void {
                foreach (self::$temporary_files as $file) {
                    /** @noinspection PhpUsageOfSilenceOperatorInspection */
                    @\unlink($file);
                }
            });
        }

        return $path;
    }
}

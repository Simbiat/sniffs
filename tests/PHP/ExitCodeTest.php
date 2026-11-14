<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\PHP;

use PHPUnit\Framework\Attributes\CoversClass;
use Simbiat\Sniffs\PHP\ExitCodeSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

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

    /**
     * The message keeps the case of the keyword as it is written, but the suggested replacement is always in lower case.
     * The fixtures check only the source codes of the results, so only a test of the messages can see the case.
     *
     * @throws \JsonException
     */
    public function testSuggestionIsInLowerCase(): void
    {
        $code = <<<'PHP'
            <?php
            EXIT;
            Die();
            exit;
            PHP;
        $messages = self::messages(PhpcsFactory::checkString($code, PhpcsFactory::forSniff(self::sniffCode())));

        self::assertSame(['EXIT should be called with an explicit exit code, e.g. exit(0).'], \array_column($messages[2], 'message'));
        self::assertSame(['Die() should be called with an explicit exit code, e.g. die(0).'], \array_column($messages[3], 'message'));
        self::assertSame(['exit should be called with an explicit exit code, e.g. exit(0).'], \array_column($messages[4], 'message'));
    }
}

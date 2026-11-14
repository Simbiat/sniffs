<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\PHP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Helpers\PregCallHelper;
use Simbiat\Sniffs\PHP\PreferWhiteStringSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;

#[CoversClass(PreferWhiteStringSniff::class)]
#[UsesClass(CallHelper::class)]
#[UsesClass(PregCallHelper::class)]
final class PreferWhiteStringTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.PHP.PreferWhiteString';
    }

    protected static function codes(): array
    {
        return [
            'PreferWhiteString' => false,
        ];
    }

    /**
     * @throws \JsonException
     */
    public function testSuggestionContainsTheSubjectAndNegation(): void
    {
        $code = <<<'PHP'
            <?php
            $a = preg_match('/^\s*$/u', $value) === 1;
            $b = preg_match('/^\s*$/u', $this->getValue()) !== 1;
            $c = preg_match('/^\s*$/u', $key, $matches) === 1;
            PHP;
        $file = PhpcsFactory::checkString($code, PhpcsFactory::forSniff(self::sniffCode()));
        $messages = self::messages($file);

        self::assertSame(
            'Consider Simbiat\StringHelpers\Sanitize::whiteString($value) instead of a hand-rolled whitespace-only regex check.',
            $messages[2][0]['message'],
        );
        self::assertSame(
            'Consider !Simbiat\StringHelpers\Sanitize::whiteString($this->getValue()) instead of a hand-rolled whitespace-only regex check.',
            $messages[3][0]['message'],
        );
        // The subject ends at the comma: the `$matches` argument is not a part of it.
        self::assertSame(
            'Consider Simbiat\StringHelpers\Sanitize::whiteString($key) instead of a hand-rolled whitespace-only regex check.',
            $messages[4][0]['message'],
        );
    }

    public function testCodeThatEndsAfterTheFunctionNameIsIgnored(): void
    {
        $file = PhpcsFactory::checkString('<?php preg_match', PhpcsFactory::forSniff(static::sniffCode()));

        self::assertSame([], self::messages($file));
    }
}

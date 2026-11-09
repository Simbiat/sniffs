<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Strings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Strings\PreferMultibyteSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;

#[CoversClass(PreferMultibyteSniff::class)]
#[UsesClass(CallHelper::class)]
final class PreferMultibyteTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.Strings.PreferMultibyte';
    }

    protected static function codes(): array
    {
        return [
            'PreferMultibyte' => false,
        ];
    }

    /**
     * @throws \JsonException
     */
    public function testMessageNamesBothFunctions(): void
    {
        $messages = self::messages($this->check(self::fixtureDirectory().'/PreferMultibyte.inc'));
        $texts = \array_column(\array_merge(...$messages), 'message');

        self::assertContains(
            'Consider mb_strlen() instead of strlen(), unless this is deliberately operating on binary/single-byte data.',
            $texts,
        );
        self::assertContains(
            'Consider mb_str_pad() instead of str_pad(), unless this is deliberately operating on binary/single-byte data.',
            $texts,
        );
    }
}

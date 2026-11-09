<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Strings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Strings\MultibyteEncodingSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;

#[CoversClass(MultibyteEncodingSniff::class)]
#[UsesClass(CallHelper::class)]
final class MultibyteEncodingTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.Strings.MultibyteEncoding';
    }

    protected static function codes(): array
    {
        return [
            'MissingEncoding' => true,
        ];
    }
}

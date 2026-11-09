<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use Simbiat\Sniffs\Tests\SniffTestCase;
use Simbiat\Sniffs\Twig\SafeFilterOptionSniff;

#[CoversClass(SafeFilterOptionSniff::class)]
final class SafeFilterOptionTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.Twig.SafeFilterOption';
    }

    protected static function codes(): array
    {
        return [
            'IsSafeUsed' => false,
        ];
    }
}

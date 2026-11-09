<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Regex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Helpers\PregCallHelper;
use Simbiat\Sniffs\Helpers\RegexLiteralHelper;
use Simbiat\Sniffs\Regex\MissingCaselessRestrictFlagSniff;
use Simbiat\Sniffs\Tests\SniffTestCase;

#[CoversClass(MissingCaselessRestrictFlagSniff::class)]
#[UsesClass(CallHelper::class)]
#[UsesClass(PregCallHelper::class)]
#[UsesClass(RegexLiteralHelper::class)]
final class MissingCaselessRestrictFlagTest extends SniffTestCase
{
    protected static function sniffCode(): string
    {
        return 'Simbiat.Regex.MissingCaselessRestrictFlag';
    }

    protected static function codes(): array
    {
        return [
            'MissingCaselessRestrictFlag' => true,
        ];
    }
}

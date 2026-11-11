<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Simbiat\Sniffs\Tests\SniffTestCase;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;
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

    /**
     * Code that is cut off in the middle of `new TwigFunction(...)`. A fixture cannot hold it, because it is not valid PHP.
     *
     * @return array<string, array{string}>
     */
    public static function incompleteCodeProvider(): array
    {
        return [
            'ends after new' => ['<?php new'],
            'ends after the class name' => ['<?php new TwigFunction'],
            'ends after the opening parenthesis' => ['<?php new TwigFunction('],
            'the parenthesis is never closed' => ["<?php new TwigFunction('a', \$c, ['is_safe' => ['html']]"],
        ];
    }

    #[DataProvider('incompleteCodeProvider')]
    public function testIncompleteCodeIsIgnored(string $code): void
    {
        $file = PhpcsFactory::checkString($code, PhpcsFactory::forSniff(static::sniffCode()));

        self::assertSame([], self::messages($file));
    }
}

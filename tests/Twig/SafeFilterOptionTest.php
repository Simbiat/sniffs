<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Tests\SniffTestCase;
use Simbiat\Sniffs\Tests\Support\PhpcsFactory;
use Simbiat\Sniffs\Twig\SafeFilterOptionSniff;

#[CoversClass(SafeFilterOptionSniff::class)]
#[UsesClass(CallHelper::class)]
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

    /**
     * The sniff looks at the first token after `new` that is not whitespace. A comment is such a token, so a comment
     * between `new` and the class name stops the check. This test records the behavior as it is now. It also shows
     * that the search starts at the token right after `new`: a search that starts one token later would skip the comment.
     *
     * A fixture cannot hold it: the comment must stand directly after `new`, and the formatting tools of the project would add a space.
     *
     * @throws \JsonException
     */
    public function testCommentRightAfterNewStopsTheCheck(): void
    {
        $code = "<?php new/* c */TwigFunction('a', \$c, ['is_safe' => ['html']]);";
        $file = PhpcsFactory::checkString($code, PhpcsFactory::forSniff(static::sniffCode()));

        self::assertSame([], self::messages($file));
    }
}

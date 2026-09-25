<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;

/**
 * Small shared helpers for sniffs that need to inspect a plain function call:
 * telling it apart from a method call/definition, and splitting its
 * top-level arguments as text (good enough for literal/keyword checks;
 * this does not build a real AST).
 */
final class CallHelper
{
    /**
     * Is this a function call or not?
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return bool
     */
    public static function isFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $prev = $phpcsFile->findPrevious(\T_WHITESPACE, $stackPtr - 1, null, true);

        if (
            $prev !== false
            && $tokens[$prev]['code'] === \T_NS_SEPARATOR
        ) {
            // PHPCS backfills PHP 8's T_NAME_FULLY_QUALIFIED / T_NAME_QUALIFIED
            // tokens into T_NS_SEPARATOR + T_STRING sequences, so `\strlen(`
            // and `Foo\strlen(` look identical at this point. The only way
            // to tell them apart is checking what comes before the separator.
            $before_separator = $phpcsFile->findPrevious(\T_WHITESPACE, $prev - 1, null, true);

            // Foo\strlen(...) or namespace\strlen(...) — a namespaced
            // function, not necessarily the same one. Leave it alone.
            return !($before_separator !== false && \in_array($tokens[$before_separator]['code'], [\T_STRING, T_NAMESPACE], true));
        }

        if (
            $prev !== false && \in_array(
                $tokens[$prev]['code'],
                [
                    \T_OBJECT_OPERATOR,
                    T_NULLSAFE_OBJECT_OPERATOR,
                    \T_DOUBLE_COLON,
                    \T_FUNCTION,
                ],
                true,
            )
        ) {
            // ->func(), Class::func(), function func() {}
            return false;
        }

        return true;
    }

    /**
     * @return string[] Raw text of each top-level argument (commas inside
     *                   nested parens/brackets/braces are ignored).
     */
    public static function splitArguments(File $phpcsFile, int $openParen, int $closeParen): array
    {
        $tokens = $phpcsFile->getTokens();
        $depth = 0;
        $current = '';
        $arguments = [];

        for ($iteration = $openParen + 1; $iteration < $closeParen; $iteration++) {
            $content = $tokens[$iteration]['content'];

            if (\in_array($tokens[$iteration]['code'], [\T_OPEN_PARENTHESIS, \T_OPEN_SQUARE_BRACKET, \T_OPEN_CURLY_BRACKET], true)) {
                $depth++;
            }
            if (\in_array($tokens[$iteration]['code'], [\T_CLOSE_PARENTHESIS, \T_CLOSE_SQUARE_BRACKET, \T_CLOSE_CURLY_BRACKET], true)) {
                $depth--;
            }

            if (
                $tokens[$iteration]['code'] === \T_COMMA
                && $depth === 0
            ) {
                $arguments[] = $current;
                $current = '';

                continue;
            }

            $current .= $content;
        }

        if (\mb_trim($current, null, 'UTF-8') !== '') {
            $arguments[] = $current;
        }

        return $arguments;
    }

    /**
     * @param string[] $arguments
     */
    public static function hasNamedEncoding(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            $argument = \mb_trim($argument, null, 'UTF-8');
            if (
                \str_starts_with($argument, 'encoding:')
                || \str_starts_with($argument, 'encoding :')
            ) {
                return true;
            }
        }

        return false;
    }
}

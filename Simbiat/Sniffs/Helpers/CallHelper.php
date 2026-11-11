<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Helpers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Small shared helpers for sniffs that need to inspect a plain function call:
 * telling it apart from a method call/definition, and splitting its
 * top-level arguments as text (good enough for literal/keyword checks;
 * this does not build a real AST).
 */
final class CallHelper
{
    /**
     * @param array<string> $arguments
     */
    public static function hasNamedEncoding(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            $argument = \mb_trim($argument, encoding: 'UTF-8');
            if (
                \str_starts_with($argument, 'encoding:')
                || \str_starts_with($argument, 'encoding :')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the token is a call of one of the given functions, and find the parentheses of the call.
     *
     * Both `strlen(...)` (T_STRING) and `\strlen(...)` (T_NAME_FULLY_QUALIFIED) are accepted.
     * Method calls, static calls and function definitions are not (see {@see self::isFunctionCall()}).
     *
     * @param File                     $phpcsFile
     * @param int                      $stackPtr  Pointer to the name token.
     * @param array<int|string, mixed> $functions Lowercase function names: values of a list, or keys of an array.
     *
     * @return array{name: string, open: int, close: int}|null `name` is lowercase, without a leading backslash.
     */
    public static function findCall(File $phpcsFile, int $stackPtr, array $functions): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $name = \mb_strtolower(\ltrim($tokens[$stackPtr]['content'], '\\'), 'UTF-8');

        $is_listed = \array_is_list($functions)
            ? \in_array($name, $functions, strict: true)
            : \array_key_exists($name, $functions);
        if (!$is_listed || !self::isFunctionCall($phpcsFile, $stackPtr)) {
            return null;
        }

        $open_parenthesis = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);
        if (
            $open_parenthesis === false
            || $tokens[$open_parenthesis]['code'] !== \T_OPEN_PARENTHESIS
            || !isset($tokens[$open_parenthesis]['parenthesis_closer'])
        ) {
            return null;
        }

        return ['name' => $name, 'open' => $open_parenthesis, 'close' => $tokens[$open_parenthesis]['parenthesis_closer']];
    }

    /**
     * Check if the last thing in the parentheses is a comma: `f($a, $b,)`. Whitespace and comments do not count.
     */
    public static function hasTrailingComma(File $phpcsFile, int $openParenthesis, int $closeParenthesis): bool
    {
        $last = $phpcsFile->findPrevious(Tokens::EMPTY_TOKENS, $closeParenthesis - 1, $openParenthesis + 1, true);

        return $last !== false && $phpcsFile->getTokens()[$last]['code'] === \T_COMMA;
    }

    /**
     * Is this a function call or not?
     *
     * A name after `->`, `?->`, `::` or `function` is a method call, a static call or a definition, not a call of a global function.
     * The check does not look at the parentheses (see {@see self::findCall()} for that).
     *
     * @param File $phpcsFile
     * @param int  $stackPtr
     *
     * @return bool
     */
    public static function isFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $prev = $phpcsFile->findPrevious(\T_WHITESPACE, $stackPtr - 1, null, true);

        return $prev === false || !\in_array(
                $tokens[$prev]['code'],
                [
                    \T_OBJECT_OPERATOR,
                    \T_NULLSAFE_OBJECT_OPERATOR,
                    \T_DOUBLE_COLON,
                    \T_FUNCTION,
                ],
                strict: true,
            );
    }

    /**
     * @param File $phpcsFile
     * @param int  $openParen
     * @param int  $closeParen
     *
     * @return array<string> Raw text of each top-level argument (commas inside
     *                       nested parens/brackets/braces are ignored).
     */
    public static function splitArguments(File $phpcsFile, int $openParen, int $closeParen): array
    {
        $tokens = $phpcsFile->getTokens();
        $depth = 0;
        $current = '';
        $arguments = [];

        for ($iteration = $openParen + 1; $iteration < $closeParen; ++$iteration) {
            $content = $tokens[$iteration]['content'];

            if (\in_array($tokens[$iteration]['code'], [\T_OPEN_PARENTHESIS, \T_OPEN_SQUARE_BRACKET, \T_OPEN_SHORT_ARRAY, \T_OPEN_CURLY_BRACKET], strict: true)) {
                ++$depth;
            }

            if (\in_array($tokens[$iteration]['code'], [\T_CLOSE_PARENTHESIS, \T_CLOSE_SQUARE_BRACKET, \T_CLOSE_SHORT_ARRAY, \T_CLOSE_CURLY_BRACKET], strict: true)) {
                --$depth;
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

        if (\mb_trim($current, encoding: 'UTF-8') !== '') {
            $arguments[] = $current;
        }

        return $arguments;
    }
}

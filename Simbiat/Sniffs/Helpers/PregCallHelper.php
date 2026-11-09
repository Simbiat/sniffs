<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Helpers;

use PHP_CodeSniffer\Files\File;

/**
 * Finds the pattern argument of a `preg_*(...)` call, when it's a plain
 * string literal (not a variable/constant/expression — those are left
 * alone, same as the original rules).
 */
final class PregCallHelper
{
    /**
     * List of functions to check.
     */
    public const array FUNCTIONS = [
        'preg_filter',
        'preg_grep',
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_replace_callback_array',
        'preg_split',
    ];

    /**
     * Everything that the regex sniffs need to know about a `preg_*()` call with a literal pattern.
     *
     * @param File $phpcsFile
     * @param int  $stackPtr  Pointer to the function name token (T_STRING or T_NAME_FULLY_QUALIFIED).
     *
     * @return array{function: string, pointer: int, content: string, flags: string}|null
     *         `function` is lowercase, `pointer` and `content` are of the pattern token, `flags` are its flags.
     *         Null if this is not a `preg_*()` call with a literal pattern that can be understood.
     */
    public static function findLiteralPattern(File $phpcsFile, int $stackPtr): ?array
    {
        $call = CallHelper::findCall($phpcsFile, $stackPtr, self::FUNCTIONS);
        if ($call === null) {
            return null;
        }

        $pattern_ptr = self::findPatternToken($phpcsFile, $stackPtr);
        if ($pattern_ptr === null) {
            return null;
        }

        $content = $phpcsFile->getTokens()[$pattern_ptr]['content'];
        $parsed = RegexLiteralHelper::parse($content);
        if ($parsed === null) {
            return null;
        }

        return ['function' => $call['name'], 'pointer' => $pattern_ptr, 'content' => $content, 'flags' => $parsed['flags']];
    }

    /**
     * @param File $phpcsFile
     * @param int  $functionNamePtr
     *
     * @return int|null Stack pointer of the pattern's T_CONSTANT_ENCAPSED_STRING
     *                  token, or null if this isn't a matching call with a literal pattern
     *                  and at least one further argument.
     */
    public static function findPatternToken(File $phpcsFile, int $functionNamePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        $open_parenthesis = $phpcsFile->findNext(\T_WHITESPACE, $functionNamePtr + 1, null, true);
        if (
            $open_parenthesis === false
            || $tokens[$open_parenthesis]['code'] !== \T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        $closed_parenthesis = $tokens[$open_parenthesis]['parenthesis_closer'];

        $pattern_ptr = $phpcsFile->findNext(\T_WHITESPACE, $open_parenthesis + 1, $closed_parenthesis, true);
        if (
            $pattern_ptr === false
            || $tokens[$pattern_ptr]['code'] !== \T_CONSTANT_ENCAPSED_STRING
        ) {
            return null;
        }

        // Require at least one more argument after the pattern, matching
        // the original rules' `$func$($pattern$, $arg$)` shape.
        $comma = $phpcsFile->findNext(\T_COMMA, $pattern_ptr + 1, $closed_parenthesis);
        if ($comma === false) {
            return null;
        }

        return $pattern_ptr;
    }
}

<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Regex;

use PHP_CodeSniffer\Files\File;

/**
 * Finds the pattern argument of a `preg_*(...)` call, when it's a plain
 * string literal (not a variable/constant/expression — those are left
 * alone, same as the original rules).
 */
final class PregCallHelper
{
    /**
     * List of functions to check
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
     * @return int|null Stack pointer of the pattern's T_CONSTANT_ENCAPSED_STRING
     *   token, or null if this isn't a matching call with a literal pattern
     *   and at least one further argument.
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

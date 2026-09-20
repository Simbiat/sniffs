<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Regex;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags preg_* calls whose literal pattern combines the `u` and `i` flags
 * without the caseless-restrict `r` flag (PCRE2's `u`+`i` without `r` does
 * full Unicode case-folding, which is slower and can match more than you
 * expect — `r` restricts it to simple case folding). Auto-fixable —
 * appends `r` to the pattern's flags.
 *
 * Only fires once a pattern already has both `u` and `i` — if it's also
 * missing `u`, MissingUnicodeFlagSniff catches that first and this one
 * will pick it up on the following run, same two-pass behavior as the
 * original SSR rules.
 *
 * Ported from the "Missing caseless restrict flag" rule.
 */
final class MissingCaselessRestrictFlagSniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * An example return value for a sniff that wants to listen for whitespace
     * and any comments would be:
     *
     * <code>
     *    return array(
     *        T_WHITESPACE,
     *        T_DOC_COMMENT,
     *        T_COMMENT,
     *    );
     * </code>
     *
     * @return array<int|string>
     *
     * @see    Tokens.php
     */
    public function register(): array
    {
        return [\T_STRING];
    }

    /**
     * Called when one of the token types that this sniff is listening for
     * is found.
     *
     * The stackPtr variable indicates where in the stack the token was found.
     * A sniff can acquire information about this token, along with all the other
     * tokens within the stack by first acquiring the token stack:
     *
     * <code>
     *    $tokens = $phpcsFile->getTokens();
     *    echo 'Encountered a '.$tokens[$stackPtr]['type'].' token';
     *    echo 'token information: ';
     *    print_r($tokens[$stackPtr]);
     * </code>
     *
     * If the sniff discovers an anomaly in the code, they can raise an error
     * by calling addError() on the \PHP_CodeSniffer\Files\File object, specifying an error
     * message and the position of the offending token:
     *
     * <code>
     *    $phpcsFile->addError('Encountered an error', $stackPtr);
     * </code>
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where the
     *                                               token was found.
     * @param int                         $stackPtr  The position in the PHP_CodeSniffer
     *                                               file's token stack where the token
     *                                               was found.
     *
     * @return void Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return `$phpcsFile->numTokens` to skip
     *                  the rest of the file.
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $function_name = mb_strtolower($tokens[$stackPtr]['content'], 'UTF-8');

        if (!\in_array($function_name, PregCallHelper::FUNCTIONS, true)) {
            return;
        }

        $pattern_ptr = PregCallHelper::findPatternToken($phpcsFile, $stackPtr);
        if ($pattern_ptr === null) {
            return;
        }

        $parsed = RegexLiteralHelper::parse($tokens[$pattern_ptr]['content']);
        if ($parsed === null) {
            return;
        }

        $flags = $parsed['flags'];
        if (
            !str_contains($flags, 'u')
            || !str_contains($flags, 'i')
            || str_contains($flags, 'r')
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableWarning(
            "Pattern for %s() combines 'u' and 'i' without the caseless restrict ('r') flag.",
            $pattern_ptr,
            'MissingCaselessRestrictFlag',
            [$function_name],
        );

        if ($fix) {
            $phpcsFile->fixer->replaceToken(
                $pattern_ptr,
                RegexLiteralHelper::appendFlag($tokens[$pattern_ptr]['content'], 'r'),
            );
        }
    }
}

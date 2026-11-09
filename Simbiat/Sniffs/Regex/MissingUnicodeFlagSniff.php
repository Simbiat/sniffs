<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Regex;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Simbiat\Sniffs\Helpers\PregCallHelper;
use Simbiat\Sniffs\Helpers\RegexLiteralHelper;

/**
 * Flags preg_* calls whose literal pattern is missing the `u` (Unicode)
 * flag. Auto-fixable — appends `u` to the pattern's flags.
 *
 * Ported from the "Missing Unicode flag" rule.
 */
final class MissingUnicodeFlagSniff implements Sniff
{
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
     * @param File $phpcsFile The PHP_CodeSniffer file where the
     *                        token was found.
     * @param int  $stackPtr  The position in the PHP_CodeSniffer
     *                        file's token stack where the token
     *                        was found.
     *
     * @return void Optionally returns a stack pointer. The sniff will not be
     *              called again on the current file until the returned stack
     *              pointer is reached. Return `$phpcsFile->numTokens` to skip
     *              the rest of the file.
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $pattern = PregCallHelper::findLiteralPattern($phpcsFile, $stackPtr);
        if (
            $pattern === null
            || \str_contains($pattern['flags'], 'u')
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableWarning(
            "Pattern for %s() is missing the Unicode ('u') flag.",
            $pattern['pointer'],
            'MissingUnicodeFlag',
            [$pattern['function']],
        );

        if ($fix) {
            $phpcsFile->fixer->replaceToken($pattern['pointer'], RegexLiteralHelper::appendFlag($pattern['content'], 'u'));
        }
    }

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
        return [\T_STRING, \T_NAME_FULLY_QUALIFIED];
    }
}

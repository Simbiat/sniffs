<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags exit/die used without an explicit status code, and auto-fixes it to
 * exit(0). Covers both `exit()`/`die()` (empty call, the original rule's
 * exact scope) and the bare `exit;`/`die;` form, which the original SSR
 * rule could not match because it required call syntax.
 *
 * Ported from the "Use exit function with exit code" rule.
 */
final class ExitCodeSniff implements Sniff
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
        return [\T_EXIT];
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
        $next = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);

        // Bare `exit;` / `die;` — no parentheses at all.
        if (
            $next === false
            || $tokens[$next]['code'] !== \T_OPEN_PARENTHESIS
        ) {
            $fix = $phpcsFile->addFixableWarning(
                '%s should be called with an explicit exit code, e.g. %s(0).',
                $stackPtr,
                'MissingCode',
                [$tokens[$stackPtr]['content'], \mb_strtolower($tokens[$stackPtr]['content'], 'UTF-8')],
            );

            if ($fix) {
                $phpcsFile->fixer->replaceToken($stackPtr, $tokens[$stackPtr]['content'].'(0)');
            }

            return;
        }

        $open_parenthesis = $next;
        $close_parenthesis = $tokens[$open_parenthesis]['parenthesis_closer'];

        // Anything between the parens (even just whitespace/comments) counts
        // as "has an argument" — only a genuinely empty exit()/die() is flagged.
        $has_argument = false;
        for ($iteration = $open_parenthesis + 1; $iteration < $close_parenthesis; $iteration++) {
            if ($tokens[$iteration]['code'] !== \T_WHITESPACE) {
                $has_argument = true;

                break;
            }
        }

        if ($has_argument) {
            return;
        }

        $fix = $phpcsFile->addFixableWarning(
            '%s() should be called with an explicit exit code, e.g. %s(0).',
            $stackPtr,
            'MissingCode',
            [$tokens[$stackPtr]['content'], \mb_strtolower($tokens[$stackPtr]['content'], 'UTF-8')],
        );

        if ($fix) {
            $phpcsFile->fixer->beginChangeset();
            $phpcsFile->fixer->addContent($open_parenthesis, '0');
            $phpcsFile->fixer->endChangeset();
        }
    }
}

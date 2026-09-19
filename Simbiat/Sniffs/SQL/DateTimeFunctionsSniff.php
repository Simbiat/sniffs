<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\SQL;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Scans string literals that look like SQL for two conventions:
 *   - UTC_TIMESTAMP/UTC_TIME/UTC_DATE should be CURRENT_TIMESTAMP/CURRENT_TIME/CURRENT_DATE.
 *   - CURRENT_TIMESTAMP/NOW()/etc. should force (6) fractional-second precision.
 *
 * Two things narrow this down from "any string containing these words":
 *   1. A string only gets scanned at all if it *starts* with a recognized
 *      SQL statement keyword - cuts out prose/log-message false positives.
 *   2. SQL comments (`-- ...` to end of line, `/* ... *\/`) are stripped
 *      before matching, so a mention inside a comment in your own SQL
 *      doesn't get flagged.
 *
 * Neither of these is exact - a false positive is still possible if
 * (for example) the word appears inside a quoted SQL string literal rather
 * than as an actual date/time function. Given the alternative is silently
 * missing real occurrences, this is the tradeoff made here; loosen or
 * tighten SQL_KEYWORDS / the comment-stripping regex if it's too noisy or
 * too quiet in practice.
 *
 * Deliberately report-only: rewriting inside a string literal safely
 * (escaping, concatenation, comments) isn't worth the risk of corrupting
 * the query.
 *
 * Ported from the "Prefer UTC to CURRENT" and "Force maximum time precision"
 * CustomRegExpInspection rules, PHP-file half only - keep the PhpStorm
 * versions of these two rules scoped to *.sql files.
 */
final class DateTimeFunctionsSniff implements Sniff
{
    /**
     * Statements that will indicate that we likely have an SQL
     */
    private const string SQL_KEYWORDS = 'SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|WITH|CALL';

    /**
     * Statements that need to be replaced by UTC versions
     */
    private const string UTC_PATTERN = '/\b(UTC_)(TIMESTAMP|TIME|DATE)\b/iur';

    /**
     * Statements that need explicit precision
     */
    private const string PRECISION_PATTERN = '/\b(CURRENT_TIMESTAMP|CURRENT_TIME|SYSDATE|NOW|CURTIME|TIMESTAMP|DATETIME)\b(?!\s*\(\s*6\s*\))/iur';

    /** Strips `-- line comments` and block comments. */
    private const string COMMENT_PATTERN = '/--[^\r\n]*|\/\*.*?\*\//siur';

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
     * @see    Tokens.php
     */
    public function register(): array
    {
        return [\T_CONSTANT_ENCAPSED_STRING, \T_DOUBLE_QUOTED_STRING, \T_HEREDOC];
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
        $raw = $tokens[$stackPtr]['content'];

        // Strip the PHP quote characters for '...'/"..." strings. A
        // T_HEREDOC token's content is already just the body — PHPCS gives
        // the `<<<SQL` marker its own separate T_START_HEREDOC token, so
        // there's no marker line to strip here.
        $content = \mb_ltrim($tokens[$stackPtr]['code'] === \T_HEREDOC ? $raw : \mb_trim($raw, "'\"", 'UTF-8'), null, 'UTF-8');

        if (\preg_match('/^(?:'.self::SQL_KEYWORDS.')\b/iur', $content) !== 1) {
            return;
        }

        $content = \preg_replace(self::COMMENT_PATTERN, '', $content) ?? $content;

        if (\preg_match(self::UTC_PATTERN, $content, $matches) === 1) {
            $phpcsFile->addWarning(
                'Use CURRENT_%s instead of %s%s.',
                $stackPtr,
                'PreferCurrent',
                [\mb_strtoupper($matches[2], 'UTF-8'), $matches[1], \mb_strtoupper($matches[2], 'UTF-8')],
            );
        }

        if (\preg_match(self::PRECISION_PATTERN, $content, $matches) === 1) {
            $phpcsFile->addWarning(
                '%s should force maximum fractional-second precision, e.g. %s(6).',
                $stackPtr,
                'MissingPrecision',
                [\mb_strtoupper($matches[1], 'UTF-8'), \mb_strtoupper($matches[1], 'UTF-8')],
            );
        }
    }
}

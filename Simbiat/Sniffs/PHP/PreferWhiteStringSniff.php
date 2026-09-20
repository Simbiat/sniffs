<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Simbiat\Sniffs\Strings\CallHelper;

/**
 * Suggests \Simbiat\StringHelpers\Sanitize::whiteString($subject) instead
 * of a hand-rolled preg_match('/^\s*$/[flags]', $subject, $matches) === 1
 * (or !== 1) whitespace-only check.
 *
 * Suggestion only, deliberately not auto-fixed: unlike the other fixable
 * sniffs here, this one changes which function actually runs the check -
 * worth a human confirming whiteString()'s behavior matches what the
 * call site actually needs before swapping it in.
 *
 * Only matches a literal pattern of exactly /^\s*$/ or /^\s+$/, single- or
 * double-quoted, plus optional flags from `imsxeADSUXJur` - a differently-
 * shaped core pattern is left alone.
 *
 * Ported from the "Prefer `whiteString()`" rule.
 */
final class PreferWhiteStringSniff implements Sniff
{
    /**
     *
     */
    private const string ALLOWED_FLAGS = 'imsxeADSUXJur';

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

        if (\mb_strtolower($tokens[$stackPtr]['content'], 'UTF-8') !== 'preg_match') {
            return;
        }

        if (!CallHelper::isFunctionCall($phpcsFile, $stackPtr)) {
            return;
        }

        $open_parenthesis = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);
        if (
            $open_parenthesis === false
            || $tokens[$open_parenthesis]['code'] !== \T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $close_parenthesis = $tokens[$open_parenthesis]['parenthesis_closer'];

        $pattern_ptr = $phpcsFile->findNext(\T_WHITESPACE, $open_parenthesis + 1, $close_parenthesis, true);
        if (
            $pattern_ptr === false
            || $tokens[$pattern_ptr]['code'] !== \T_CONSTANT_ENCAPSED_STRING
        ) {
            return;
        }

        $pattern_content = $tokens[$pattern_ptr]['content'];
        if (!$this->isWhitespaceOnlyPattern($pattern_content)) {
            return;
        }

        // Second argument ($keep - the subject variable) must be present.
        $comma = $phpcsFile->findNext(\T_COMMA, $pattern_ptr + 1, $close_parenthesis);
        if ($comma === false) {
            return;
        }
        $subject_ptr = $phpcsFile->findNext(\T_WHITESPACE, $comma + 1, $close_parenthesis, true);
        if ($subject_ptr === false) {
            return;
        }
        $subject_end = $phpcsFile->findNext([\T_COMMA], $subject_ptr + 1, $close_parenthesis);
        $subject_end = $subject_end === false ? $close_parenthesis : $subject_end;
        $subject = \mb_trim($phpcsFile->getTokensAsString($subject_ptr, $subject_end - $subject_ptr), null, 'UTF-8');

        // Comparison operator right after the call: === 1 or !== 1.
        $after_call = $phpcsFile->findNext(\T_WHITESPACE, $close_parenthesis + 1, null, true);
        if (
            $after_call === false
            || !\in_array($tokens[$after_call]['code'], [\T_IS_IDENTICAL, \T_IS_NOT_IDENTICAL], true)
        ) {
            return;
        }
        $one_ptr = $phpcsFile->findNext(\T_WHITESPACE, $after_call + 1, null, true);
        if (
            $one_ptr === false
            || $tokens[$one_ptr]['code'] !== \T_LNUMBER
            || $tokens[$one_ptr]['content'] !== '1'
        ) {
            return;
        }

        $negated = $tokens[$after_call]['code'] === \T_IS_NOT_IDENTICAL;
        $suggestion = ($negated ? '!' : '').'\Simbiat\StringHelpers\Sanitize::whiteString('.$subject.')';

        $phpcsFile->addWarning(
            'Consider %s instead of a hand-rolled whitespace-only regex check.',
            $stackPtr,
            'PreferWhiteString',
            [$suggestion],
        );
    }

    /**
     * Content is the raw token text, quotes included, e.g. '/^\s*$/ui' or
     * "/^\s+$/". True for either quote style, and for either /^\s*$/ or
     * /^\s+$/ as the pattern core, with flags drawn exclusively from
     * ALLOWED_FLAGS.
     */
    private function isWhitespaceOnlyPattern(string $content): bool
    {
        $inner = \mb_substr($content, 1, -1, 'UTF-8'); // strip the surrounding quote char

        $core = null;
        foreach (['/^\s*$/', '/^\s+$/'] as $candidate) {
            if (str_starts_with($inner, $candidate)) {
                $core = $candidate;
                break;
            }
        }

        if ($core === null) {
            return false;
        }

        $flags = \mb_substr($inner, \mb_strlen($core, 'UTF-8'), null, 'UTF-8');

        return $flags === '' || \preg_match('/^['.\preg_quote(self::ALLOWED_FLAGS, '/').']+$/u', $flags) === 1;
    }
}

<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Simbiat\Sniffs\Helpers\CallHelper;
use Simbiat\Sniffs\Helpers\PregCallHelper;
use Simbiat\StringHelpers\Sanitize;

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
     * Allowed PCRE2 flags.
     */
    private const string ALLOWED_FLAGS = 'imsxeADSUXJur';

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
        $tokens = $phpcsFile->getTokens();

        // Checks the function name (without the leading backslash of T_NAME_FULLY_QUALIFIED), that it is a call of the global function, and the parentheses.
        $call = CallHelper::findCall($phpcsFile, $stackPtr, ['preg_match']);
        if ($call === null) {
            return;
        }

        // A literal pattern, and a second argument (the subject) must be present.
        $arguments = PregCallHelper::findPatternAndComma($phpcsFile, $stackPtr);
        if ($arguments === null) {
            return;
        }

        if (!self::isWhitespaceOnlyPattern($tokens[$arguments['pattern']]['content'])) {
            return;
        }

        $close_parenthesis = $call['close'];
        $subject_ptr = $phpcsFile->findNext(\T_WHITESPACE, $arguments['comma'] + 1, $close_parenthesis, true);
        if ($subject_ptr === false) {
            return;
        }

        // The subject ends at the next comma (the `$matches` argument follows), or at the closing parenthesis.
        // The search starts at the subject itself: its first token is never a comma.
        $subject_end = $phpcsFile->findNext(\T_COMMA, $subject_ptr, $close_parenthesis);
        $subject_end = $subject_end === false
            ? $close_parenthesis
            : $subject_end;
        $subject = \mb_trim($phpcsFile->getTokensAsString($subject_ptr, $subject_end - $subject_ptr), encoding: 'UTF-8');

        // Comparison operator right after the call: === 1 or !== 1.
        $after_call = $phpcsFile->findNext(\T_WHITESPACE, $close_parenthesis + 1, null, true);
        if (
            $after_call === false
            || !\in_array($tokens[$after_call]['code'], [\T_IS_IDENTICAL, \T_IS_NOT_IDENTICAL], strict: true)
        ) {
            return;
        }

        $one_ptr = $phpcsFile->findNext(\T_WHITESPACE, $after_call + 1, null, true);
        // Only the integer literal `1` has the content `1`, so the content check is enough, and the code is not checked.
        if (
            $one_ptr === false
            || $tokens[$one_ptr]['content'] !== '1'
        ) {
            return;
        }

        $negated = $tokens[$after_call]['code'] === \T_IS_NOT_IDENTICAL;
        $suggestion = ($negated ? '!' : '').Sanitize::class.'::whiteString('.$subject.')';

        $phpcsFile->addWarning(
            'Consider %s instead of a hand-rolled whitespace-only regex check.',
            $stackPtr,
            'PreferWhiteString',
            [$suggestion],
        );
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

    /**
     * Content is the raw token text, quotes included, e.g. '/^\s*$/ui' or
     * "/^\s+$/". True for either quote style, and for either /^\s*$/ or
     * /^\s+$/ as the pattern core, with flags drawn exclusively from
     * ALLOWED_FLAGS.
     *
     * @param string $content
     *
     * @return bool
     */
    private static function isWhitespaceOnlyPattern(string $content): bool
    {
        // Strip the surrounding quote char. The quotes are ASCII, and so is the rest of what is compared below.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- See above.
        $inner = \substr($content, 1, -1);
        $core = \array_find(['/^\s*$/', '/^\s+$/'], static fn($candidate): bool => \str_starts_with($inner, $candidate));

        if ($core === null) {
            return false;
        }

        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- See above.
        $flags = \substr($inner, \strlen($core));

        // Every character of the flags must be one of the allowed flags. An empty string means "no flags", which is fine.
        return \strspn($flags, self::ALLOWED_FLAGS) === \strlen($flags);
    }
}

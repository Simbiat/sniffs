<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Twig;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags `new TwigFunction(..., ['is_safe' => ...])`: a reminder to double-check
 * that output sanitization is actually applied wherever `is_safe` is
 * used, since it tells Twig to skip its own auto-escaping.
 *
 * Only `TwigFunction`/`TwigFilter` (bare or leading-backslash-qualified) are
 * matched — a namespaced class that merely shares the name is left alone.
 *
 * Ported from the "`is_safe` option used" rule. Not auto-fixable — this is a
 * "go look at this" reminder, not a mechanical defect.
 */
final class SafeFilterOptionSniff implements Sniff
{
    /**
     * Classes to check
     */
    private const array CLASSES = ['TwigFunction', 'TwigFilter'];

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
        return [\T_NEW];
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

        $class_name_ptr = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);
        if ($class_name_ptr === false) {
            return;
        }

        // Allow `new \TwigFunction(...)` — skip a single leading separator,
        // same reasoning as Strings\CallHelper::isFunctionCall().
        if ($tokens[$class_name_ptr]['code'] === \T_NS_SEPARATOR) {
            $class_name_ptr = $phpcsFile->findNext(\T_WHITESPACE, $class_name_ptr + 1, null, true);
        }

        if (
            $class_name_ptr === false
            || $tokens[$class_name_ptr]['code'] !== \T_STRING
        ) {
            return;
        }

        $class_name = $tokens[$class_name_ptr]['content'];
        if (!\in_array($class_name, self::CLASSES, true)) {
            return;
        }

        // A following T_NS_SEPARATOR means this was actually `Some\TwigFunction`
        // — a different, namespaced class. Bail.
        $after_name = $phpcsFile->findNext(\T_WHITESPACE, $class_name_ptr + 1, null, true);
        if (
            $after_name !== false
            && $tokens[$after_name]['code'] === \T_NS_SEPARATOR
        ) {
            return;
        }

        $open_parenthesis = $after_name;
        if (
            $open_parenthesis === false
            || $tokens[$open_parenthesis]['code'] !== \T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $close_parenthesis = $tokens[$open_parenthesis]['parenthesis_closer'];

        for ($iteration = $open_parenthesis + 1; $iteration < $close_parenthesis; $iteration++) {
            if ($tokens[$iteration]['code'] === \T_CONSTANT_ENCAPSED_STRING
                && \mb_trim($tokens[$iteration]['content'], "'\"", 'UTF-8') === 'is_safe'
            ) {
                $phpcsFile->addWarning(
                    "'is_safe' on %s bypasses Twig's auto-escaping — confirm the callable's output is actually sanitised.",
                    $stackPtr,
                    'IsSafeUsed',
                    [$class_name],
                );

                return;
            }
        }
    }
}

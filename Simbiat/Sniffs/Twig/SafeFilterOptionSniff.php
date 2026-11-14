<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Twig;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Simbiat\Sniffs\Helpers\CallHelper;

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
     * Classes to check.
     */
    private const array CLASSES = ['TwigFunction', 'TwigFilter'];

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

        $class_name_ptr = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);
        // `$tokens[false]` is the first token (`<?php`), which is never a class name: without this check, the code below returns anyway.
        // @infection-ignore-all
        if ($class_name_ptr === false) {
            return;
        }

        // `new \TwigFunction` is a single T_NAME_FULLY_QUALIFIED token: strip its leading backslash.
        // A longer name (`\Twig\TwigFunction`) will not match the list, so it stays ignored.
        $class_name = \ltrim($tokens[$class_name_ptr]['content'], '\\');
        if (!\in_array($class_name, self::CLASSES, strict: true)) {
            return;
        }

        $parentheses = CallHelper::findParentheses($phpcsFile, $class_name_ptr);
        if ($parentheses === null) {
            return;
        }

        ['open' => $open_parenthesis, 'close' => $close_parenthesis] = $parentheses;

        // The tokens from the opening parenthesis up to, but not including, the closing one. Neither of them is a string.
        // The loop goes over a slice, not over a counter: a broken counter makes an endless loop, which fills the memory and kills the test process.
        $length = $close_parenthesis - $open_parenthesis;
        foreach (\array_slice($tokens, $open_parenthesis, $length) as $token) {
            if (
                $token['code'] === \T_CONSTANT_ENCAPSED_STRING
                && \mb_trim($token['content'], "'\"", 'UTF-8') === 'is_safe'
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
}

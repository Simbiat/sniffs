<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags mb_* calls that rely on the internal/default encoding instead of
 * stating one explicitly. Safe to auto-fix: it only adds an argument,
 * it never changes which function is called.
 *
 * Ported from the "Explicit multibyte encoding" SSR rules.
 */
final class MultibyteEncodingSniff implements Sniff
{
    /**
     * Functions that accept an explicit encoding argument, mapped to their
     * TOTAL parameter count (encoding included). `encoding` is the last
     * parameter in every one of these (verified against PHP 8.3/8.4 via
     * Reflection) so "given arg count >= this number" means encoding is
     * already supplied, whatever its value.
     *
     * mb_lcfirst/mb_ltrim/mb_rtrim/mb_trim/mb_ucfirst were added in PHP 8.4 —
     * drop them here if the project targets an older PHP version.
     */
    private const array FUNCTIONS = [
        'mb_check_encoding' => 2,
        'mb_chr' => 2,
        'mb_convert_case' => 3,
        'mb_convert_encoding' => 3,
        'mb_convert_kana' => 3,
        'mb_decode_numericentity' => 3,
        'mb_encode_numericentity' => 3,
        'mb_http_output' => 1,
        'mb_lcfirst' => 2,
        'mb_ltrim' => 3,
        'mb_ord' => 2,
        'mb_rtrim' => 3,
        'mb_scrub' => 2,
        'mb_str_pad' => 5,
        'mb_str_split' => 3,
        'mb_strcut' => 4,
        'mb_strimwidth' => 5,
        'mb_stripos' => 4,
        'mb_stristr' => 4,
        'mb_strlen' => 2,
        'mb_strpos' => 4,
        'mb_strrchr' => 4,
        'mb_strrichr' => 4,
        'mb_strripos' => 4,
        'mb_strrpos' => 4,
        'mb_strstr' => 4,
        'mb_strtolower' => 2,
        'mb_strtoupper' => 2,
        'mb_strwidth' => 2,
        'mb_substr' => 4,
        'mb_substr_count' => 3,
        'mb_trim' => 3,
        'mb_ucfirst' => 2,
    ];

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
        $function_name = \mb_strtolower($tokens[$stackPtr]['content'], 'UTF-8');

        if (!\array_key_exists($function_name, self::FUNCTIONS)) {
            return;
        }

        if (!CallHelper::isFunctionCall($phpcsFile, $stackPtr)) {
            return;
        }

        $open_parenthesis = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);
        if (
            !$open_parenthesis
            || $tokens[$open_parenthesis]['code'] !== \T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $close_parenthesis = $tokens[$open_parenthesis]['parenthesis_closer'];
        $params = CallHelper::splitArguments($phpcsFile, $open_parenthesis, $close_parenthesis);

        // Encoding slot already reached positionally, or supplied by name.
        /** @noinspection OffsetOperationsInspection https://github.com/kalessil/phpinspectionsea/issues/1941 */
        if (
            \count($params) >= self::FUNCTIONS[$function_name]
            || CallHelper::hasNamedEncoding($params)
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableWarning(
            'Call to %s() should state the encoding explicitly instead of relying on the internal/default one.',
            $stackPtr,
            'MissingEncoding',
            [$function_name],
        );

        if ($fix) {
            $phpcsFile->fixer->beginChangeset();
            // Named argument: always valid here regardless of how many
            // optional parameters sit between the last given argument and
            // the encoding slot, so this never needs to know the exact
            // position — only that the slot is not yet filled (checked above).
            $prefix = \count($params) > 0 && \mb_trim(\end($params), null, 'UTF-8') !== '' ? ', ' : '';
            $phpcsFile->fixer->addContentBefore($close_parenthesis, $prefix."encoding: 'UTF-8'");
            $phpcsFile->fixer->endChangeset();
        }
    }
}

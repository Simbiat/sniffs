<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Simbiat\Sniffs\Helpers\CallHelper;

/**
 * Suggests a multibyte-safe alternative to single-byte string functions.
 *
 * Deliberately never auto-fixed (no addFixableWarning): these functions are
 * sometimes the *correct* choice, e.g. strlen()/substr() on binary data,
 * where mb_strlen()/mb_substr() would give the wrong answer. A human needs
 * to look at each call site.
 *
 * Ported from the "Prefer multibyte alternative" SSR rules.
 */
final class PreferMultibyteSniff implements Sniff
{
    /**
     * What functions should be replaced with what alternatives.
     */
    private const array MAP = [
        'chr' => 'mb_chr',
        'lcfirst' => 'mb_lcfirst',
        'ltrim' => 'mb_ltrim',
        'ord' => 'mb_ord',
        'rtrim' => 'mb_rtrim',
        'stripos' => 'mb_stripos',
        'stristr' => 'mb_stristr',
        'strlen' => 'mb_strlen',
        'strpos' => 'mb_strpos',
        'strrchr' => 'mb_strrchr',
        'strripos' => 'mb_strripos',
        'strrpos' => 'mb_strrpos',
        'strstr' => 'mb_strstr',
        'strtolower' => 'mb_strtolower',
        'strtoupper' => 'mb_strtoupper',
        'str_pad' => 'mb_str_pad',
        'str_split' => 'mb_str_split',
        'substr' => 'mb_substr',
        'substr_count' => 'mb_substr_count',
        'trim' => 'mb_trim',
        'ucfirst' => 'mb_ucfirst',
    ];

    /**
     * Variable-name substrings suggesting binary data - a match on the
     * first argument suppresses the warning entirely (never autofixed
     * either way; see the class docblock for why).
     */
    private const array NAME_HINTS = [
        'binary',
        'blob',
        'bytes',
        'raw',
        'hash',
        'digest',
        'checksum',
        'signature',
        'ciphertext',
        'plaintext',
        'salt',
        'iv',
        'key',
    ];

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
        $call = CallHelper::findCall($phpcsFile, $stackPtr, self::MAP);
        if ($call === null) {
            return;
        }

        $function_name = $call['name'];
        if (self::firstArgumentLooksBinary($phpcsFile, $call['open'])) {
            return;
        }

        $phpcsFile->addWarning(
            'Consider %s() instead of %s(), unless this is deliberately operating on binary/single-byte data.',
            $stackPtr,
            'PreferMultibyte',
            [self::MAP[$function_name], $function_name],
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
     * Check if argument looks binary.
     *
     * @param File $phpcsFile
     * @param int  $openParen
     *
     * @return bool
     */
    private static function firstArgumentLooksBinary(File $phpcsFile, int $openParen): bool
    {
        $tokens = $phpcsFile->getTokens();

        $first_arg_ptr = $phpcsFile->findNext(\T_WHITESPACE, $openParen + 1, null, true);
        if (
            $first_arg_ptr === false
            || $tokens[$first_arg_ptr]['code'] !== \T_VARIABLE
        ) {
            return false;
        }

        // Split `$rawBody`, `$user_key` or `$IV_BYTES` into words, and compare whole words only.
        // A substring match would also catch `$archive` (iv), `$monkey` (key) or `$crawler` (raw).
        // The patterns are constant and the subject is a variable name, so preg_replace() cannot fail here.
        $name = (string)\preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', \ltrim($tokens[$first_arg_ptr]['content'], '$'));

        foreach (\explode('_', \mb_strtolower($name, 'UTF-8')) as $word) {
            if (\in_array($word, self::NAME_HINTS, strict: true) || \in_array((string)\preg_replace('/(?:es|s)$/', '', $word), self::NAME_HINTS, strict: true)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Helpers;

/**
 * Parses the delimiter/flags off a PHP string token that holds a PCRE
 * pattern literal, e.g. the token content `'/^\s*$/ui'` (quotes included,
 * as PHPCS gives it to you).
 *
 * Mirrors the delimiter-detection logic from the original PhpStorm rules
 * exactly: first character of the string content is taken as the
 * delimiter, and the *last* occurrence of that same character marks where
 * the flags start. This does not handle bracket-style delimiters
 * (`(...)`, `{...}`, `[...]`, `<...>`) correctly, where PCRE expects a
 * matching closing bracket rather than a repeat of the opening one — the
 * original rules had the same limitation, so this isn't a new gap.
 */
final class RegexLiteralHelper
{
    /**
     * Returns the token content with `$flag` inserted right before the
     * closing quote (i.e. appended to the flags).
     *
     * @param string $tokenContent
     * @param string $flag
     *
     * @return string
     */
    public static function appendFlag(string $tokenContent, string $flag): string
    {
        // The closing quote is one ASCII byte, so cutting by bytes is safe for valid UTF-8.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- The cut is at an ASCII character.
        return \substr($tokenContent, 0, -1).$flag.\substr($tokenContent, -1);
    }

    /**
     * @param string $tokenContent
     *
     * @return array{flags: string}|null
     */
    public static function parse(string $tokenContent): ?array
    {
        if ($tokenContent === '') {
            return null;
        }

        $quote = $tokenContent[0];
        if (
            $quote !== "'"
            && $quote !== '"'
        ) {
            // Not a literal string token at all (shouldn't happen given how
            // callers register, but keeps this safe to call standalone).
            return null;
        }

        // The quotes are ASCII, and so is the delimiter in every pattern that PHP accepts here: byte functions give the same result.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- See above.
        $inner = \substr($tokenContent, 1, -1);
        if ($inner === '') {
            return null;
        }

        $delimiter = $inner[0];
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- See above.
        $last_delimiter = \strrpos($inner, $delimiter);
        if (
            $last_delimiter === false
            || $last_delimiter <= 0
        ) {
            return null;
        }

        return [
            'flags' => \substr($inner, $last_delimiter + 1),
        ];
    }
}

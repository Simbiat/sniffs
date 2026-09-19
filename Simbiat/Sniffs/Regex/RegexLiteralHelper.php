<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\Regex;

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
     * @return array{flags: string, delimPos: int}|null `delimPos` is the
     *   offset of the *opening* delimiter within the raw token content
     *   (right after the quote), so callers can locate where the flags end
     *   (= end of string, before the closing quote) without re-parsing.
     */
    public static function parse(string $tokenContent): ?array
    {
        if (\mb_strlen($tokenContent, 'UTF-8') < 2) {
            return null;
        }

        $quote = $tokenContent[0];
        if ($quote !== "'" && $quote !== '"') {
            // Not a literal string token at all (shouldn't happen given how
            // callers register, but keeps this safe to call standalone).
            return null;
        }

        $inner = \mb_substr($tokenContent, 1, -1, 'UTF-8');
        if ($inner === '') {
            return null;
        }

        $delimiter = $inner[0];
        $last_delimiter = \mb_strrpos($inner, $delimiter, 0, 'UTF-8');
        if ($last_delimiter === false || $last_delimiter <= 0) {
            return null;
        }

        return [
            'flags' => \mb_substr($inner, $last_delimiter + 1, encoding: 'UTF-8'),
        ];
    }

    /**
     * Returns the token content with `$flag` inserted right before the
     * closing quote (i.e. appended to the flags).
     */
    public static function appendFlag(string $tokenContent, string $flag): string
    {
        return \mb_substr($tokenContent, 0, -1, 'UTF-8').$flag.\mb_substr($tokenContent, -1, encoding: 'UTF-8');
    }
}

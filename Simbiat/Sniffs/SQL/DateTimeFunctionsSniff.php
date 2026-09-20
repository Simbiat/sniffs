<?php

declare(strict_types=1);

namespace Simbiat\Sniffs\SQL;

use JetBrains\PhpStorm\Pure;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Scans string literals that look like SQL for two conventions, both
 * auto-fixable:
 *   - UTC_TIMESTAMP/UTC_TIME/UTC_DATE should be CURRENT_TIMESTAMP/CURRENT_TIME/CURRENT_DATE.
 *   - CURRENT_TIMESTAMP/NOW()/etc. should state an explicit fractional-second precision
 * (configurable, see the `precision` property below).
 *
 * Two things narrow detection down from "any string containing these
 * words":
 *   1. A string only gets scanned at all if it *starts* with a recognized
 *      SQL statement keyword - cuts out prose/log-message false positives.
 *   2. SQL comments (`-- ...` to end of line, block comments - now
 *      correctly matched even when a block comment spans multiple
 *      fragments) are masked out before matching, so a mention inside a
 *      comment is ignored and never rewritten.
 *
 * Neither the SQL-keyword gate nor the comment detection is exact - a false
 * positive is still possible if the word appears inside a quoted SQL string
 * literal rather than as an actual date/time function. Given the
 * alternative is silently missing (or mis-rewriting) real occurrences,
 * that's the tradeoff made here.
 *
 * Ported from the "Prefer UTC to CURRENT" and "Force maximum time precision"
 * CustomRegExpInspection rules, PHP-file half only - keep the PhpStorm
 * versions of these two rules scoped to *.sql files.
 */
final class DateTimeFunctionsSniff implements Sniff
{
    /**
     * Required fractional-second precision. Configure per-project via
     * ruleset.xml:
     *   <rule ref="Simbiat.SQL.DateTimeFunctions">
     *       <properties>
     *           <property name="precision" value="6"/>
     *       </properties>
     *   </rule>
     * MySQL/MariaDB accept 0-6; anything outside that range is clamped
     * rather than silently producing invalid SQL. Defaults to 0 (require
     * an explicit precision to be stated, without assuming everyone wants
     * microsecond precision) - this package doesn't assume your value.
     */
    public int $precision = 0;
    /**
     * Statements that will indicate that we likely have an SQL
     */
    private const string SQL_KEYWORDS = 'SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|WITH|CALL';

    /**
     * Statements that need to be replaced by UTC versions
     */
    private const string UTC_DETECT_PATTERN = '/\b(UTC_)(TIMESTAMP|TIME|DATE)\b/iur';

    /**
     * Statements that need explicit precision
     */
    private const string PRECISION_DETECT_PATTERN = '/\b(CURRENT_TIMESTAMP|CURRENT_TIME|UTC_TIMESTAMP|UTC_TIME|SYSDATE|NOW|CURTIME|TIMESTAMP|DATETIME)\b(?!\s*\(\s*%d\s*\))/iur';

    /**
     * Fix pattern - captures any existing (digit) clause so it can be normalized rather than appending a second one.
     */
    private const string PRECISION_FIX_PATTERN = '/\b(CURRENT_TIMESTAMP|CURRENT_TIME|UTC_TIMESTAMP|UTC_TIME|SYSDATE|NOW|CURTIME|TIMESTAMP|DATETIME)\b(\s*\(\s*([0-6]?)\s*\))?/i';

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
     *
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
        $is_heredoc = $tokens[$stackPtr]['code'] === \T_HEREDOC;

        if (!$this->isFragmentStart($tokens, $stackPtr, $is_heredoc)) {
            // A continuation/closing fragment of a multi-line string - the
            // call for its starting fragment handles the whole literal.
            return;
        }

        $fragment_parts = $this->collectFragments($tokens, $stackPtr, $is_heredoc);

        $pieces = [];
        foreach ($fragment_parts as $ptr) {
            $pieces[] = $tokens[$ptr]['content'];
        }

        $quote = '';
        if (!$is_heredoc) {
            $quote = $pieces[0][0];
            $last_index = \count($pieces) - 1;
            $pieces[0] = \mb_substr($pieces[0], 1, encoding: 'UTF-8');
            $pieces[$last_index] = \mb_substr($pieces[$last_index], 0, -1, 'UTF-8');
        }

        $body = \implode('', $pieces);

        $trimmed_for_gate = \mb_ltrim($body, encoding: 'UTF-8');
        if (\preg_match('/^'.self::SQL_KEYWORDS.'\b/i', $trimmed_for_gate) !== 1) {
            return;
        }

        $masked = \preg_replace_callback(
            self::COMMENT_PATTERN,
            static fn(array $m): string => \str_repeat(' ', \mb_strlen($m[0], 'UTF-8')),
            $body,
        );

        $edits = [];

        $wants_utc_fix = \preg_match(self::UTC_DETECT_PATTERN, $masked, $utc_match) === 1;
        if ($wants_utc_fix) {
            $fix = $phpcsFile->addFixableWarning(
                'Use CURRENT_%s instead of %s%s.',
                $stackPtr,
                'PreferCurrent',
                [\mb_strtoupper($utc_match[2], 'UTF-8'), $utc_match[1], \mb_strtoupper($utc_match[2], 'UTF-8')],
            );
            if ($fix) {
                $edits = \array_merge($edits, $this->collectUtcEdits($masked));
            }
        }

        $wants_precision_fix = \preg_match($this->precisionDetectPattern(), $masked, $precision_match) === 1;
        if ($wants_precision_fix) {
            $fix = $phpcsFile->addFixableWarning(
                '%s should explicitly state (%d) fractional-second precision.',
                $stackPtr,
                'MissingPrecision',
                [\mb_strtoupper($precision_match[1], 'UTF-8'), $this->normalisedPrecision()],
            );
            if ($fix) {
                $edits = \array_merge($edits, $this->collectPrecisionEdits($masked));
            }
        }

        if ($edits === []) {
            return;
        }

        $edits = $this->mergeOverlappingEdits($edits);

        \usort($edits, static fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
        foreach ($edits as $edit) {
            $body = \substr_replace($body, $edit['replacement'], $edit['offset'], $edit['length']);
        }

        // Split back across the ORIGINAL fragment boundaries. Our edits
        // never add/remove newlines, so the newline count - and therefore
        // the fragment count - is unchanged; if that ever stops being true
        // (e.g. an unexpected edge case), bail out instead of risking a
        // mis-split that corrupts the file.
        $new_pieces = \preg_split('/(?<=\n)/', $body, -1, \PREG_SPLIT_NO_EMPTY) ?: [$body];
        if (
            !\is_array($new_pieces)
            || \count($new_pieces) !== \count($fragment_parts)
        ) {
            return;
        }

        if (!$is_heredoc) {
            $last_index = \count($new_pieces) - 1;
            $new_pieces[0] = $quote.$new_pieces[0];
            $new_pieces[$last_index] .= $quote;
        }

        $phpcsFile->fixer->beginChangeset();
        foreach ($fragment_parts as $iteration => $ptr) {
            if ($tokens[$ptr]['content'] !== $new_pieces[$iteration]) {
                $phpcsFile->fixer->replaceToken($ptr, $new_pieces[$iteration]);
            }
        }
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Is it a start of the fragment
     *
     * @param array $tokens
     * @param int   $stackPtr
     * @param bool  $isHeredoc
     *
     * @return bool
     */
    private function isFragmentStart(array $tokens, int $stackPtr, bool $isHeredoc): bool
    {
        if ($isHeredoc) {
            $prev_code = $tokens[$stackPtr - 1]['code'] ?? null;

            return $prev_code !== \T_HEREDOC;
        }

        $content = $tokens[$stackPtr]['content'];

        return $content !== '' && ($content[0] === "'" || $content[0] === '"');
    }

    /** @return list<int> */
    private function collectFragments(array $tokens, int $startPtr, bool $isHeredoc): array
    {
        $ptrs = [$startPtr];

        if ($isHeredoc) {
            $ptr = $startPtr;
            while (($tokens[$ptr + 1]['code'] ?? null) === \T_HEREDOC) {
                $ptr++;
                $ptrs[] = $ptr;
            }

            return $ptrs;
        }

        $quote_char = $tokens[$startPtr]['content'][0];
        $code = $tokens[$startPtr]['code'];

        if ($this->endsWithUnescapedQuote($tokens[$startPtr]['content'], $quote_char, true)) {
            return $ptrs; // single-fragment, complete string
        }

        $ptr = $startPtr;
        while (
            \array_key_exists($ptr + 1, $tokens)
            && $tokens[$ptr + 1]['code'] === $code
        ) {
            $ptr++;
            $ptrs[] = $ptr;

            if ($this->endsWithUnescapedQuote($tokens[$ptr]['content'], $quote_char, false)) {
                break;
            }
        }

        return $ptrs;
    }

    /**
     * True if $content ends with $quoteChar preceded by an even number of
     * backslashes (i.e. the quote is a real delimiter, not escaped).
     * $strippingLeadingQuote: whether $content still has its own opening
     * quote at position 0 (only true for a single, self-contained fragment).
     */
    private function endsWithUnescapedQuote(string $content, string $quoteChar, bool $isFirstFragment): bool
    {
        $min_length = $isFirstFragment ? 2 : 1;
        if (
            \mb_strlen($content, 'UTF-8') < $min_length
            || !\str_ends_with($content, $quoteChar)
        ) {
            return false;
        }

        $backslashes = 0;
        $iteration = \mb_strlen($content, 'UTF-8') - 2;
        while (
            $iteration >= 0
            && $content[$iteration] === '\\'
        ) {
            $backslashes++;
            $iteration--;
        }

        return $backslashes % 2 === 0;
    }

    /** @return list<array{type: string, offset: int, length: int, replacement: string}> */
    private function collectUtcEdits(string $masked): array
    {
        $edits = [];

        $matching_result = \preg_match_all(self::UTC_DETECT_PATTERN, $masked, $matches, \PREG_OFFSET_CAPTURE);
        if (
            $matching_result === false
            || $matching_result < 1
        ) {
            return $edits;
        }

        foreach ($matches[0] as $iteration => [$full_match, $offset]) {
            $utc_prefix = $matches[1][$iteration][0];
            $suffix = $matches[2][$iteration][0];

            $replacement_prefix = ctype_upper(\str_replace('_', '', $utc_prefix)) ? 'CURRENT_' : 'current_';

            $edits[] = [
                'type' => 'utc',
                'offset' => $offset,
                'length' => \mb_strlen($full_match, 'UTF-8'),
                'replacement' => $replacement_prefix.$suffix,
            ];
        }

        return $edits;
    }

    /**
     * UTC_TIMESTAMP/UTC_TIME are matched by BOTH this sniff's rules (they
     * need renaming to CURRENT_*, and - like CURRENT_TIMESTAMP/CURRENT_TIME -
     * they take a precision argument too). When both rules are enabled
     * a single occurrence can produce an `utc` edit and a `precision` edit
     * at the identical offset. Applying two overlapping substr_replace
     * calls corrupts the string, so overlapping pairs are combined into
     * one edit here instead - the precision edit's target digit, applied
     * to the UTC edit's already-corrected keyword.
     *
     * @param list<array{type: string, offset: int, length: int, replacement: string, keyword?: string, target?: string}> $edits
     *
     * @return list<array{type: string, offset: int, length: int, replacement: string}>
     */
    private function mergeOverlappingEdits(array $edits): array
    {
        $by_offset = [];
        foreach ($edits as $edit) {
            $by_offset[$edit['offset']][] = $edit;
        }

        $merged = [];
        foreach ($by_offset as $offset => $group) {
            if (\count($group) === 1) {
                $merged[] = $group[0];
                continue;
            }

            $utc = null;
            $precision = null;
            foreach ($group as $edit) {
                if ($edit['type'] === 'utc') {
                    $utc = $edit;
                }
                if ($edit['type'] === 'precision') {
                    $precision = $edit;
                }
            }

            if ($utc !== null && $precision !== null) {
                $merged[] = [
                    'type' => 'merged',
                    'offset' => $offset,
                    'length' => \max($utc['length'], $precision['length']),
                    'replacement' => $utc['replacement'].'('.$precision['target'].')',
                ];
                continue;
            }

            // Shouldn't normally happen (would mean two edits of the same
            // type landed on the same offset) - don't silently drop data.
            $merged[] = $group[0];
        }

        return $merged;
    }

    /**
     * @return string
     */
    #[Pure]
    private function precisionDetectPattern(): string
    {
        return \sprintf(self::PRECISION_DETECT_PATTERN, $this->normalisedPrecision());
    }

    /**
     * @return int
     */
    private function normalisedPrecision(): int
    {
        return \max(0, \min(6, $this->precision));
    }

    /** @return list<array{type: string, offset: int, length: int, replacement: string, keyword: string, target: string}> */
    private function collectPrecisionEdits(string $masked): array
    {
        $edits = [];
        $target = (string) $this->normalisedPrecision();

        $matching_result = \preg_match_all(self::PRECISION_FIX_PATTERN, $masked, $matches, \PREG_OFFSET_CAPTURE);
        if (
            $matching_result === false
            || $matching_result < 1
        ) {
            return $edits;
        }

        foreach ($matches[0] as $iteration => [$full_match, $offset]) {
            $digit = $matches[3][$iteration][0] ?? '';

            if ($digit === $target) {
                continue;
            }

            $keyword = $matches[1][$iteration][0];

            $edits[] = [
                'type' => 'precision',
                'offset' => $offset,
                'length' => \mb_strlen($full_match, 'UTF-8'),
                'replacement' => $keyword.'('.$target.')',
                'keyword' => $keyword,
                'target' => $target,
            ];
        }

        return $edits;
    }
}

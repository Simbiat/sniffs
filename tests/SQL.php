<?php

// Suppressing some inspections, since this is a test file, and is not supposed to be "correct"
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter, SlevomatCodingStandard.Variables.UnusedVariable
// phpcs:disable Symfony.Commenting, PSR1.Files.SideEffects, Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.ControlStructures.LanguageConstructWithParentheses.UsedWithParentheses
// phpcs:disable SlevomatCodingStandard.Commenting, PSR12.Files.FileHeader.SpacingAfterDocblockBlock
// phpcs:disable PSR1.Classes.ClassDeclaration, SlevomatCodingStandard.Classes.RequireAbstractOrFinal
// phpcs:disable Universal.Classes.RequireFinalClass, SlevomatCodingStandard.Files.TypeNameMatchesFileName
// phpcs:disable Universal.PHP.DisallowExitDieParentheses, Universal.PHP.RequireExitDieParentheses
// phpcs:disable SlevomatCodingStandard.PHP.RequireNowdoc, Squiz.PHP.CommentedOutCode,
// phpcs:disable SlevomatCodingStandard.Whitespaces.DuplicateSpaces
/** @noinspection PhpEnforceDocCommentInspection, PhpRedundantOptionalArgumentInspection, PhpUnusedParameterInspection */
/** @noinspection PhpMissingDocCommentInspection, PhpUndefinedVariableInspection, PhpUndefinedClassInspection */
/** @noinspection UnusedFunctionResultInspection, PhpExpressionResultUnusedInspection, AutoloadingIssuesInspection */
/** @noinspection PhpUnreachableStatementInspection, PhpIllegalPsrClassPathInspection, SqlResolveInspection */
/** @noinspection CompositionAndInheritanceInspection */

declare(strict_types=1);

/**
 * Exhaustive fixture for Simbiat.SQL.DateTimeFunctions - both the
 * "UTC_* -> CURRENT_*" and "force (6) precision" rules, auto-fixable.
 */

// === UTC_* -> CURRENT_*: all three keywords, case preserved on fix ===
$utc1 = 'SELECT UTC_TIMESTAMP()';     // -> CURRENT_TIMESTAMP()
$utc2 = 'SELECT UTC_TIME()';          // -> CURRENT_TIME()
$utc3 = 'SELECT UTC_DATE()';          // -> CURRENT_DATE()
$utc4 = 'select utc_timestamp()';     // -> select current_timestamp() (lowercase preserved)

// === Missing (6) precision: one line per recognized keyword, bare form ===
$precision_1 = 'SELECT CURRENT_TIMESTAMP';  // -> CURRENT_TIMESTAMP(6)
$precision_2 = 'SELECT CURRENT_TIME';       // -> CURRENT_TIME(6)
$precision_3 = 'SELECT SYSDATE';            // -> SYSDATE(6)
$precision_4 = 'SELECT NOW';                // -> NOW(6)
$precision_5 = 'SELECT CURTIME';            // -> CURTIME(6)
$precision_6 = 'SELECT TIMESTAMP';          // -> TIMESTAMP(6)
$precision_7 = 'SELECT DATETIME';           // -> DATETIME(6)

// === Precision: the three paren shapes that need normalizing ===
$paren_bare = 'SELECT NOW()';          // empty parens -> NOW(6)
$paren_wrong = 'SELECT CURTIME(3)';    // wrong digit -> CURTIME(6)
$paren_spaced = 'SELECT TIMESTAMP( 2 )'; // whitespace inside -> TIMESTAMP(6)

// === Precision: already correct, must NOT fire ===
$already_fine = 'SELECT SYSDATE(6)';

// === Both rules, single phpcbf pass fixes both ===
$both = 'SELECT UTC_TIMESTAMP AS `column` FROM `table` WHERE `created` = NOW()';

// === Gate: doesn't start with a recognized SQL keyword -> ignored entirely ===
$prose = 'The UTC_TIMESTAMP is stored for audit purposes, refreshed via NOW().';

// === Comment-safety: occurrence only inside a SQL comment -> ignored, untouched ===
$line_comment = 'SELECT `id` FROM `table` -- old code used UTC_TIMESTAMP() and NOW()
      WHERE id = 1';
$block_comment = 'SELECT `id` FROM `table` /* old approach used UTC_TIMESTAMP() and NOW() */ WHERE id = 1';

// === Comment-safety + real fix together: comment mention left alone, real occurrence fixed ===
$mixed_comment = 'SELECT UTC_TIMESTAMP() AS `column` FROM `table` -- see UTC_TIMESTAMP() in the old version
      WHERE `id` = 1';

// === Heredoc form ===
$heredoc = <<<SQL
SELECT NOW() AS `column` FROM dual
SQL;

// === Heredoc spanning multiple lines, issue on a NON-FIRST line ===
// PHPCS tokenizes every heredoc as one token per physical line. This
// line specifically exercises that the sniff reconstructs the full body
// before scanning, rather than only ever checking the first line.
$heredoc_multiline = <<<SQL
SELECT `id`
FROM dual
WHERE `ts` = UTC_TIMESTAMP()
SQL;

<?php

// Suppressing some inspections, since this is a test file, and is not supposed to be "correct"
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter, SlevomatCodingStandard.Variables.UnusedVariable
// phpcs:disable Symfony.Commenting, PSR1.Files.SideEffects, Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.ControlStructures.LanguageConstructWithParentheses.UsedWithParentheses
// phpcs:disable SlevomatCodingStandard.Commenting, PSR12.Files.FileHeader.SpacingAfterDocblockBlock
// phpcs:disable PSR1.Classes.ClassDeclaration, SlevomatCodingStandard.Classes.RequireAbstractOrFinal
// phpcs:disable Universal.Classes.RequireFinalClass, SlevomatCodingStandard.Files.TypeNameMatchesFileName
// phpcs:disable Universal.PHP.DisallowExitDieParentheses, Universal.PHP.RequireExitDieParentheses
/* @noinspection PhpEnforceDocCommentInspection, PhpRedundantOptionalArgumentInspection, PhpUnusedParameterInspection */
/* @noinspection PhpMissingDocCommentInspection, PhpUndefinedVariableInspection, PhpUndefinedClassInspection */
/* @noinspection UnusedFunctionResultInspection, PhpExpressionResultUnusedInspection, AutoloadingIssuesInspection */
/* @noinspection PhpUnreachableStatementInspection, PhpIllegalPsrClassPathInspection, SqlResolveInspection */
/* @noinspection PhpStatementHasEmptyBodyInspection, MissingOrEmptyGroupStatementInspection */

declare(strict_types=1);

// Should be flagged + auto-fixable (missing exit code).
exit();
die();
if (true) {
    exit;
}

// Should NOT be flagged (exit code present).
exit(1);

// Should be flagged, never auto-fixable.
$fn = new TwigFunction('foo', $callable, ['is_safe' => ['html']]);

// Should NOT be flagged (no `is_safe` key).
$fn2 = new TwigFunction('bar', $callable, ['needs_environment' => true]);

// Prefer whiteString(): === 1 form
if (preg_match('/^\s*$/u', $keep, $rest) === 1) {
    // suggests \Simbiat\StringHelpers\Sanitize::whiteString($keep)
}

// Prefer whiteString(): !== 1 form, two-arg call (no $rest)
if (preg_match('/^\s*$/u', $keep2) !== 1) {
    // suggests !\Simbiat\StringHelpers\Sanitize::whiteString($keep2)
}

// Different pattern core: NOT a whiteString candidate (still gets the flag-rule warnings above)
if (preg_match('/^\s+$/u', $keep3, $rest) === 1) {
    // no whiteString suggestion - \s+ isn't \s*
}

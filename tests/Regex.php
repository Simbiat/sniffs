<?php

// Suppressing some inspections, since this is a test file, and is not supposed to be "correct"
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter, SlevomatCodingStandard.Variables.UnusedVariable
// phpcs:disable Symfony.Commenting, PSR1.Files.SideEffects, Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.ControlStructures.LanguageConstructWithParentheses.UsedWithParentheses
// phpcs:disable SlevomatCodingStandard.Commenting, PSR12.Files.FileHeader.SpacingAfterDocblockBlock
// phpcs:disable PSR1.Classes.ClassDeclaration, SlevomatCodingStandard.Classes.RequireAbstractOrFinal
// phpcs:disable Universal.Classes.RequireFinalClass, SlevomatCodingStandard.Files.TypeNameMatchesFileName
// phpcs:disable Universal.PHP.DisallowExitDieParentheses, Universal.PHP.RequireExitDieParentheses
/** @noinspection PhpEnforceDocCommentInspection, PhpRedundantOptionalArgumentInspection, PhpUnusedParameterInspection */
/** @noinspection PhpMissingDocCommentInspection, PhpUndefinedVariableInspection, PhpUndefinedClassInspection */
/** @noinspection UnusedFunctionResultInspection, PhpExpressionResultUnusedInspection, AutoloadingIssuesInspection */
/** @noinspection PhpUnreachableStatementInspection, PhpIllegalPsrClassPathInspection, SqlResolveInspection */

declare(strict_types=1);

// === Missing Unicode flag ===
preg_match('/foo/', $subject, $matches);        // -> '/foo/u'
preg_match('/foo/i', $subject, $matches);       // -> '/foo/iu' (order phpcs appends in doesn't matter to PCRE)

// === Has u, no i: neither flag rule fires ===
preg_match('/foo/u', $subject, $matches);

// === Has u + i, missing r (caseless restrict) ===
preg_match('/foo/ui', $subject, $matches);      // -> '/foo/uir'

// === Has u + i + r already: neither rule fires ===
preg_match('/foo/uir', $subject, $matches);

// === Missing both u and r (has i only): two-pass convergence ===
// Pass 1 (MissingUnicodeFlag) -> '/foo/iu'; pass 2 (MissingCaselessRestrictFlag) -> '/foo/iur'
preg_match('/foo/i', $subject, $matches);

// === Non-literal pattern (variable): ignored by both flag rules ===
preg_match($pattern, $subject, $matches);

// === Only one argument (no subject): ignored, structurally incomplete ===
preg_match('/foo/');

// === Covers the other preg_* functions too, not just preg_match ===
preg_replace('/foo/', $replacement, $subject);   // -> '/foo/u'
preg_split('/foo/', $subject);                   // -> '/foo/u'
preg_grep('/foo/', $array);                      // -> '/foo/u'

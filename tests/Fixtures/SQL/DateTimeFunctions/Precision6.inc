<?php

declare(strict_types=1);

// @property precision=6

// === Configured precision is 6 ===
$sql = 'SELECT NOW() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT NOW(0) FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT NOW(3) FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT UTC_TIMESTAMP() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT UTC_TIMESTAMP(6) FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent
$sql = 'SELECT UTC_DATE() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent
$sql = 'CREATE TABLE t (created DATETIME NOT NULL)'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision

// === Already correct: must NOT be flagged ===
$sql = 'SELECT NOW(6) FROM t';
$sql = 'SELECT CURRENT_TIMESTAMP(6) FROM t';
$sql = 'SELECT CURRENT_DATE FROM t';

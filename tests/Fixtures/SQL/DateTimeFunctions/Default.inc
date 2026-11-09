<?php

declare(strict_types=1);

// === Precision missing or different from the configured one (default: 0) ===
$sql = 'SELECT NOW() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT NOW(3) FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT CURRENT_TIMESTAMP FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT CURRENT_TIME FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT SYSDATE() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT CURTIME() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT now() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'CREATE TABLE t (created DATETIME NOT NULL, updated TIMESTAMP)'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision

// === UTC_* functions: both rules apply to UTC_TIMESTAMP and UTC_TIME ===
$sql = 'SELECT UTC_TIMESTAMP() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT UTC_TIME FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'select utc_timestamp() from t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT UTC_TIMESTAMP(0) FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent

// === UTC_DATE has no precision, so only PreferCurrent applies ===
$sql = 'SELECT UTC_DATE() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent
$sql = 'SELECT UTC_DATE FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent

// === Several functions in one string: one result per code, all occurrences are fixed ===
$sql = 'SELECT NOW(), UTC_TIMESTAMP(), CURRENT_TIME FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision

// === Every SQL keyword that opens the gate ===
$sql = 'INSERT INTO t (created) VALUES (NOW())'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'UPDATE t SET updated = NOW()'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'DELETE FROM t WHERE created < NOW()'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'REPLACE INTO t (created) VALUES (NOW())'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'ALTER TABLE t ADD created DATETIME'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'WITH x AS (SELECT NOW()) SELECT * FROM x'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'CALL do_something(NOW())'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = '   SELECT NOW()'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision

// === Quote styles ===
$sql = "SELECT NOW() FROM t"; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = "SELECT NOW() FROM t WHERE id = $id"; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT \'x\', NOW() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision

// === Multi-line strings: the result is reported on the first line ===
// @expect-next Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT
    NOW()
FROM t';

// @expect-next Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = "SELECT
    UTC_TIMESTAMP(),
    id
FROM t";

// === Heredoc: the result is reported on the first line of the body ===
// @expect+2 Simbiat.SQL.DateTimeFunctions.PreferCurrent Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = <<<SQL
    SELECT UTC_TIMESTAMP(), id
    FROM t
    SQL;

// === Comments are ignored ===
$sql = 'SELECT 1 -- NOW() is not used here';
$sql = 'SELECT 1 /* NOW() is not used here */';
// @expect-next Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT
    /* a block comment that spans
       several lines, with UTC_TIMESTAMP() inside */
    NOW()
FROM t';

// === Already correct: must NOT be flagged ===
$sql = 'SELECT NOW(0) FROM t';
$sql = 'SELECT NOW( 0 ) FROM t';
$sql = 'SELECT CURRENT_TIMESTAMP(0) FROM t';
$sql = 'CREATE TABLE t (created DATETIME(0) NOT NULL)';
$sql = 'SELECT CURRENT_DATE FROM t';

// === The string does not start with an SQL keyword: must NOT be flagged ===
$message = 'Now it is NOW() o\'clock';
$message = 'Run SELECT NOW() to see the time';
$message = 'UTC_TIMESTAMP';
$message = '';
$message = 'S';

// === Not a whole word: must NOT be flagged ===
$sql = 'SELECT now_value, my_now, nowhere FROM t';
$sql = 'SELECT utc_timestamps FROM t';

// === Nowdoc is not checked (the sniff registers T_HEREDOC only) ===
$sql = <<<'SQL'
    SELECT NOW()
    SQL;

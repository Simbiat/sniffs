<?php

declare(strict_types=1);

// @property precision=9

// === A precision above 6 is treated as 6 ===
$sql = 'SELECT NOW() FROM t'; // @expect Simbiat.SQL.DateTimeFunctions.MissingPrecision
$sql = 'SELECT NOW(6) FROM t';

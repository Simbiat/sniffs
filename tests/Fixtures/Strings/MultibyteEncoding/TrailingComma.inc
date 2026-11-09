<?php

declare(strict_types=1);

// === Calls with a trailing comma: the fixed code must stay valid PHP ===
mb_strlen($str,); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_strlen($str, ); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_substr( // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
    $str,
    0,
    5,
);

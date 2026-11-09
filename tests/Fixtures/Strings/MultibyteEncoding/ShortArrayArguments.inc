<?php

declare(strict_types=1);

// === A literal short array as an argument: flagged, same as the form with a variable ===
mb_encode_numericentity($str, $map); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding
mb_encode_numericentity($str, [0x80, 0x10FFFF, 0, 0xFFFFFF]); // @expect Simbiat.Strings.MultibyteEncoding.MissingEncoding

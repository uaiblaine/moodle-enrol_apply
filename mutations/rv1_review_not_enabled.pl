# RV1: the reviewed key-only mode does not count as accepting keys, so no key resolves there.
s#\$mode === self::MODE_REQUIRED \|\| \$mode === self::MODE_REVIEW;#\$mode === self::MODE_REQUIRED;#;

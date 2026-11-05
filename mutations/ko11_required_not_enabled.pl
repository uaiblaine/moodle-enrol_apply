# KO11: key-only mode does not count as accepting keys, so no key ever resolves there.
s#return \$mode === self::MODE_ANY \|\| \$mode === self::MODE_REQUIRED \|\| \$mode === self::MODE_REVIEW;#return \$mode === self::MODE_ANY || \$mode === self::MODE_REVIEW;#;

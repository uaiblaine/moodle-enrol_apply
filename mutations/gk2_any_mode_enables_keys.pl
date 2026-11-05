# GK2: any stored value other than "off" turns the option on, so a restored unknown mode admits.
s#return \$mode === self::MODE_ANY \|\| \$mode === self::MODE_REQUIRED \|\| \$mode === self::MODE_REVIEW;#return \$mode !== self::MODE_OFF;#;

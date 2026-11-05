# RV4: the reviewed mode admits like the other key modes.
s#\[self::MODE_ANY, self::MODE_REQUIRED\], true\);#[self::MODE_ANY, self::MODE_REQUIRED, self::MODE_REVIEW], true);#;

# RV2: the reviewed mode does not make the key mandatory, so no key leaves an ordinary application.
s#\[self::MODE_REQUIRED, self::MODE_REVIEW\], true\);#[self::MODE_REQUIRED], true);#;

# NG8: the form writes the group marker after the people picked by name.
s#array_merge\(\[self::GROUP\], \$named\)#array_merge(\$named, [self::GROUP])#;

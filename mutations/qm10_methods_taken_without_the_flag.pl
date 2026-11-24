# QM10: the "By course" view asks for the places taken without the flag, so a method with no places reads 0.
s#\$states = static::page_states\(\$known, \$now, true\);#\$states = static::page_states(\$known, \$now);#;

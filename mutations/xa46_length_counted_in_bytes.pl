# XA46: the length is counted in bytes, so a multibyte value at the column's length is refused.
s#\\core_text::strlen\(\$value\)#strlen(\$value)#;

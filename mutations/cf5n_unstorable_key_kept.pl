# CF5N: a key too long for the column, or carrying a control character, is stored anyway.
s{if \(\\core_text::strlen\(\$key\) > self::KEY_LENGTH \|\| preg_match\('/\[\\x00-\\x1F\\x7F\]/', \$key\)\) \{\n\s*return null;\n\s*\}\n}{}s;

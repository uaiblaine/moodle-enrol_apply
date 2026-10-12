# CF4E: the snapshot freezes what was typed into a question its condition hid.
s{if \(isset\(\$hidden\[\(int\) \$id\]\)\) \{\n\s*continue;\n\s*\}\n(\s*\$name = self::element_name)}{$1}s;

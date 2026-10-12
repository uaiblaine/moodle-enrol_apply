# CF4D: validate() checks a question its condition hid.
s{if \(isset\(\$hidden\[\(int\) \$id\]\)\) \{\n\s*continue;\n\s*\}\n(\s*\$element = self::element_name)}{$1}s;

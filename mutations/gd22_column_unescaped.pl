# GD22: the report's "Group chosen" writes the name unescaped into a raw cell.
s#return format_string\(\(string\) \$value, true, \[\n\s*'context' => \\context_course::instance\(\(int\) \$row->chosengroupcourseid\),\n\s*\]\);#return (string) \$value;#;

# QG1: the key group's name goes into the cell raw; flexible_table writes cells unescaped.
# Re-pointed when the cell's content moved to applications::keygroup_html().
s#return format_string\(\$row->keygroupname, true, \[\n\s*'context' => context_course::instance\(\(int\) \$row->keygroupcourseid\),\n\s*\]\);#return \$row->keygroupname;#;

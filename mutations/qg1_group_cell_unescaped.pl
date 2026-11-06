# QG1: the key group's name goes into the cell raw; flexible_table writes cells unescaped.
s#return \$label \. format_string\(\$row->keygroupname, true, \[\n\s*'context' => context_course::instance\(\(int\) \$row->keygroupcourseid\),\n\s*\]\);#return \$label . \$row->keygroupname;#;

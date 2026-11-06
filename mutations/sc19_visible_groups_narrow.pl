# SC19: visible groups narrow like separate ones.
s#\(int\) groups_get_course_groupmode\(\$course\) !== SEPARATEGROUPS\) \{\n(\s*)return false;\n(\s*)\}\n\n(\s*)\$context = context_course::instance\(\$courseid, IGNORE_MISSING\);#(int) groups_get_course_groupmode(\$course) === NOGROUPS) {\n${1}return false;\n${2}}\n\n${3}\$context = context_course::instance(\$courseid, IGNORE_MISSING);#;

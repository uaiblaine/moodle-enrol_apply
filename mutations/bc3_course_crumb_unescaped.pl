# BC3: the review page's course crumb is the short name raw.
s#\$navbar->add\(format_string\(\$course->shortname, true, \['context' => context_course::instance\(\(int\) \$course->id\)\]\)\);#\$navbar->add(\$course->shortname);#;

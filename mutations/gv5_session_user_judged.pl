# GV5: the capability is judged for the session's user instead of the reader.
s#has_capability\('moodle/course:viewhiddengroups', \$context, \$readerid\)#has_capability('moodle/course:viewhiddengroups', \$context)#;

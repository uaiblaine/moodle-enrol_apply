# GV5: the capability is judged for the session's user instead of the reader.
s#groupscope::holds_in_course\('moodle/course:viewhiddengroups', \$context, \$readerid\)#groupscope::holds_in_course('moodle/course:viewhiddengroups', \$context)#;

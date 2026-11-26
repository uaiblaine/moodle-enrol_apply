# GV8: visible_ids() reads moodle/course:viewhiddengroups with has_capability() alone, a lapsed course role included.
s#\? groupscope::holds_in_course\('moodle/course:viewhiddengroups', \$context, \$readerid\)#? has_capability('moodle/course:viewhiddengroups', \$context, \$readerid)#;

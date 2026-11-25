# QG23: the page-at-once visibility ignores moodle/course:viewhiddengroups.
s#\? groupscope::holds_in_course\('moodle/course:viewhiddengroups', \$context, \$readerid\)#? false#;

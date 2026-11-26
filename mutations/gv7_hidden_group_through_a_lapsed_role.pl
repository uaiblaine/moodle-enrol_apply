# GV7: visible_to() reads moodle/course:viewhiddengroups with has_capability() alone, a lapsed course role included.
s#if \(groupscope::holds_in_course\('moodle/course:viewhiddengroups', \$context, \$readerid\)\) \{#if (has_capability('moodle/course:viewhiddengroups', \$context, \$readerid)) {#;

# GV2: holding moodle/course:viewhiddengroups does not show a hidden group.
s#if \(groupscope::holds_in_course\('moodle/course:viewhiddengroups', \$context, \$readerid\)\) \{#if (false) {#;

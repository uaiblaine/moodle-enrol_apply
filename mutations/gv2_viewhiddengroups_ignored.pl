# GV2: holding moodle/course:viewhiddengroups does not show a hidden group.
s#if \(has_capability\('moodle/course:viewhiddengroups', \$context, \$readerid\)\) \{#if (false) \{#;

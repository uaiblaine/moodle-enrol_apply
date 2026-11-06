# QG23: the page-at-once visibility ignores moodle/course:viewhiddengroups.
s#\? has_capability\('moodle/course:viewhiddengroups', \$context, \$readerid\)#? false#;

# FG12: the warning that a moderator could approve another moderator is never given.
s#if \(array_intersect\(\$roleids, \\enrol_apply\\local\\decidingrole::roles\(\$courseid\)\)\) \{#if (false) {#;

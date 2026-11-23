# FG21: the warning is read from the saved role again, as before S1, not from the offered ones.
s#if \(array_intersect\(\$roleids, \\enrol_apply\\local\\decidingrole::roles\(\$courseid\)\)\) \{#if (\\enrol_apply\\local\\decidingrole::method_decides(\$instance)) {#;

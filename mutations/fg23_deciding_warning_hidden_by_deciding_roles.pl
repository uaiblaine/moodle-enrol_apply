# FG23: the role condition lists every offered role, the deciding ones included.
s#\$learner = array_values\(array_diff\(\$roleids, \\enrol_apply\\local\\decidingrole::roles\(\$courseid\)\)\);#\$learner = array_values(\$roleids);#;

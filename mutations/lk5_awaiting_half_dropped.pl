# LK5: an application awaiting a decision (pending or deferred) no longer locks.
s#\\enrol_apply\\local\\queue::is_awaiting_decision\(\$row\)\n(\s+)&& \\enrol_apply\\local\\decidingrole::method_decides\(\$row\)#false\n$1&& \\enrol_apply\\local\\decidingrole::method_decides(\$row)#;

# KW4: only the key's own group is checked for seats, not the instance's list.
s#\$full = \\enrol_apply\\local\\groupseats::full\(\$targets\);#\$full = \\enrol_apply\\local\\groupseats::full([\$keygroupid]);#;

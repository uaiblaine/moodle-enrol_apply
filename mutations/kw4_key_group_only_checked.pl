# KW4: only the key's own group is checked for seats, not the instance's list.
s#if \(\\enrol_apply\\local\\groupseats::full\(\$targets\)\) \{#if (\\enrol_apply\\local\\groupseats::full([\$keygroupid])) \{#;

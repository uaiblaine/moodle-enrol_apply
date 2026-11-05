# KO5: a key-only instance whose locks timed out leaves a flagged application instead of refusing.
s#if \(\$mandatory\) \{(\n\s*return \\enrol_apply\\local\\application_result::refused\(get_string\('keyseatunconfirmed')#if (false) \{${1}#;

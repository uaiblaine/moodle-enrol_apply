# KO3: a key-only instance with no place left leaves a flagged application instead of refusing.
s#if \(\$required\) \{(\n\s*return \\enrol_apply\\local\\application_result::refused\(get_string\('keynoplace')#if (false) \{${1}#;

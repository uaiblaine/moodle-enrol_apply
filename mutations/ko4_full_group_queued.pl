# KO4: a key-only instance with a full group leaves a flagged application instead of refusing.
s#if \(\$required\) \{(\n\s*return \\enrol_apply\\local\\application_result::refused\(\$this->group_full_refusal)#if (false) \{${1}#;

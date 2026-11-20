# WD3: allows_group(), which the notices read, does not read "Who decides".
s#(return true;\n\s*\}\n\n\s*)if \(\$enrolid > 0 && self::decided_by_all_groups\(\$enrolid\)\) \{#$1if (false) {#;

# WD5: the choosers do not read "Who decides".
s#(return \$groups;\n\s*\}\n\s*)if \(\$enrolid > 0 && self::decided_by_all_groups\(\$enrolid\)\) \{#$1if (false) {#;

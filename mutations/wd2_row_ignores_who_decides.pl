# WD2: the per-row check does not read "Who decides".
s#(\[\x27id\x27 => \$userenrolmentid\]\);\n\s*)if \(\$enrolid > 0 && self::decided_by_all_groups\(\$enrolid\)\) \{#$1if (false) {#;

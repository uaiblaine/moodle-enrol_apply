# SW1: a switched role does not lift the system exemption, so a site administrator previewing as a
# moderator lists every group's applications.
s#if \(\$userid === \(int\) \$USER->id && self::switched\(\$courseid\)\) \{#if (false) {#;

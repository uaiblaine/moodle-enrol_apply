# SW2: the current user's switch is applied to whoever the question is about.
s#if \(\$userid === \(int\) \$USER->id && self::switched\(\$courseid\)\) \{#if (self::switched(\$courseid)) {#;

# SW3: the narrowing reads the system grant directly again.
s#if \(self::system_exempt\(\$courseid, \$userid, \$capability\)\) \{#if (has_capability(\$capability, context_system::instance(), \$userid)) {#;

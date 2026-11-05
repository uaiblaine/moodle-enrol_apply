# SC52: the system exemption reads the deciding capability whatever is exercised.
s#if \(has_capability\(\$capability, context_system::instance\(\), \$userid\)\) \{#if (has_capability(self::DECIDE, context_system::instance(), \$userid)) {#;

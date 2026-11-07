# SC52: the system exemption reads the deciding capability whatever is exercised.
s#return has_capability\(\$capability, context_system::instance\(\), \$userid\);#return has_capability(self::DECIDE, context_system::instance(), \$userid);#;

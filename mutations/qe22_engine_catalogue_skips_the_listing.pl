# QE22: the engine's catalogue() names the columns for any reader, trusting the caller to have asked.
s#(\$instance = \$table->get_instance\(\);)\n\s*self::require_listing\(\(int\) \$instance->id\);#$1#;

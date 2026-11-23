# QE20: the engine's rows() reads the queue for any reader, trusting the caller to have asked.
s#(function rows\(int \$enrolid, array \$options\): array \{)\n\s*self::require_listing\(\$enrolid\);\n#$1\n#;

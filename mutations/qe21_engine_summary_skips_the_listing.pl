# QE21: the engine's summary() reads the figures for any reader, trusting the caller to have asked.
s#(function summary\(\?stdClass \$instance\): array \{)\n\s*self::require_listing\(\(int\) \(\$instance->id \?\? 0\)\);\n#$1\n#;

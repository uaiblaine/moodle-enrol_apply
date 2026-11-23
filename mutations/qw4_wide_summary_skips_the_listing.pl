# QW4: summary() asks the listing question for a method and reads the wide figures for anybody.
s#self::require_listing\(\(int\) \(\$instance->id \?\? 0\)\);\n(\s*)\n(\s*)if \(\$instance === null\) \{\n(\s*)return self::wide_summary\(\);#if (\$instance !== null) {\n$2    self::require_listing((int) \$instance->id);\n$2}\n$1\n$2if (\$instance === null) {\n$3return self::wide_summary();#;

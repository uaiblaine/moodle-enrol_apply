# LP25: holds_in_course() refuses a system-level holder whose enrolment in the course lapsed.
s#return self::system_exempt\(\(int\) \$context->instanceid, \$reader, \$capability\) \|\| !self::lapsed\(\$context, \$reader\);#return !self::lapsed(\$context, \$reader);#;

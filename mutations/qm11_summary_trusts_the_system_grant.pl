# QM11: the wide summary skips the per-course question for a reader holding the capability in the system context.
s#\$shown = array_filter\(#\$shown = has_capability('moodle/course:viewhiddencourses', context_system::instance()) ? \$instances : array_filter(#;

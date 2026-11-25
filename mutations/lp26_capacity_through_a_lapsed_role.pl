# LP26: the wide readers are told a hidden course's capacity through a role kept from a lapsed enrolment.
s#return \$visible \|\| groupscope::holds_in_course\('moodle/course:viewhiddencourses', (context_course::instance\(\$courseid\))\);#return \$visible || has_capability('moodle/course:viewhiddencourses', $1);#;

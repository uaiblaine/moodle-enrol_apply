# QM9: the wide readers are told a method's capacity whatever the course's visibility to them.
s#return \$visible \|\| groupscope::holds_in_course\('moodle/course:viewhiddencourses', context_course::instance\(\$courseid\)\);#return true;#;

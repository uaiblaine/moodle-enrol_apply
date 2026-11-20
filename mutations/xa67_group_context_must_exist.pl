# XA67: the group's course context is read without IGNORE_MISSING, which throws for a missing course.
s#context_course::instance\(\$group->courseid, IGNORE_MISSING\)#context_course::instance(\$group->courseid)#;

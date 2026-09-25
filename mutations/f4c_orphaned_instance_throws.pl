# F4C: resolve the listing's course context with MUST_EXIST. An {enrol} row whose course is gone
# then throws a database exception out of get_context() instead of getting the refusal an
# unknown id gets.
s#context_course::instance\(\(int\) \$instance->courseid, IGNORE_MISSING\)#context_course::instance(\$instance->courseid, MUST_EXIST)#s;

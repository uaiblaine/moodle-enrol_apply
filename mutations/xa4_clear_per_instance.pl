# XA4: a valid key for review clears the count of the instance, not of the course.
s#(A valid key ends the tries, as an admission does\.\n\s*\\enrol_apply\\local\\keyattempts::clear\(\(int\) \$instance->)courseid#${1}id#;

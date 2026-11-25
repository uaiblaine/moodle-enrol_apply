# LP3: a reader who views the course by their role (moodle/course:view) reads as lapsed when their own enrolment did.
s#return !is_viewing\(\$context, \$userid\)\n\s*&& is_enrolled#return is_enrolled#;

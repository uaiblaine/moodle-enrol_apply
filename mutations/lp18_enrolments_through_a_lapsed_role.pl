# LP18: the review page lists the applicant's other enrolments through a lapsed course role.
s#!\\enrol_apply\\local\\groupscope::holds_in_course\('moodle/course:enrolreview', \$coursecontext\)#!has_capability('moodle/course:enrolreview', \$coursecontext)#;

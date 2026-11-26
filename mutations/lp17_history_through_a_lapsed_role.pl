# LP17: the review page shows the earlier applications through a lapsed course role.
s#!\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:viewreports', \$coursecontext\)#!has_capability('enrol/apply:viewreports', \$coursecontext)#;

# LP14: the review page offers the choosers through a lapsed course role.
s#\$decides && \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:choosedecision', \$coursecontext\)#\$decides && has_capability('enrol/apply:choosedecision', \$coursecontext)#;

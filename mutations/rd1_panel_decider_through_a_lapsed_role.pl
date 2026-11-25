# RD1: the review page's panel names the decider through a lapsed course role.
s#\$namesdecider = \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:viewreports', \$coursecontext\);#\$namesdecider = has_capability('enrol/apply:viewreports', \$coursecontext);#;

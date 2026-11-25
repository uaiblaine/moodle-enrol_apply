# LP13: the review page reads a course decider through a lapsed course role (the group facts, the summary).
s#&& \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$coursecontext\);#&& has_capability('enrol/apply:manageapplications', \$coursecontext);#;

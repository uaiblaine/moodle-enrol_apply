# LP15: the review page names the course's groups through a lapsed deciding role.
s#return \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$coursecontext\)\n#return has_capability('enrol/apply:manageapplications', \$coursecontext)\n#;

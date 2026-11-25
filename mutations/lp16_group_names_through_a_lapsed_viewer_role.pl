# LP16: the review page names the course's groups through a lapsed viewing role.
s#\|\| \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:viewapplications', \$coursecontext\);#|| has_capability('enrol/apply:viewapplications', \$coursecontext);#;

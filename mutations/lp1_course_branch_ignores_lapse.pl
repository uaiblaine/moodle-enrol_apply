# LP1: can_manage_application()'s course branch decides on the capability alone, whatever the reader's own enrolment.
s#(&& has_capability\('enrol/apply:manageapplications', \$coursecontext\)\n)\s*&& !\\enrol_apply\\local\\groupscope::lapsed\(\$coursecontext, \(int\) \$USER->id\)\n#$1#;

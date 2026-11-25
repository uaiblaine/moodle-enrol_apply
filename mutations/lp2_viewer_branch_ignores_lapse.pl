# LP2: can_view_application()'s viewer branch reads the capability alone, whatever the reader's own enrolment.
s#(&& has_capability\(\$view, \$coursecontext\)\n)\s*&& !\\enrol_apply\\local\\groupscope::lapsed\(\$coursecontext, \(int\) \$USER->id\)\n#$1#;

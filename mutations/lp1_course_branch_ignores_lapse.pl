# LP1: can_manage_application()'s course branch reads the capability with has_capability() alone.
s#\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$coursecontext\)\n#has_capability('enrol/apply:manageapplications', \$coursecontext)\n#;

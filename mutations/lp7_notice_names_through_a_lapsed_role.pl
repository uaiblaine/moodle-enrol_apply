# LP7: the new-application notice names a group to a mentor through their lapsed course role.
s#\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$coursecontext, \$recipientid\)#has_capability('enrol/apply:manageapplications', \$coursecontext, \$recipientid)#;

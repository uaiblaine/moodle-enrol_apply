# LP21: the snapshot's masking reads moodle/site:viewuseridentity in a course through a lapsed role.
s#\\enrol_apply\\local\\groupscope::holds_in_course\('moodle/site:viewuseridentity', \$context, \$userid\)#has_capability('moodle/site:viewuseridentity', \$context, \$userid)#;

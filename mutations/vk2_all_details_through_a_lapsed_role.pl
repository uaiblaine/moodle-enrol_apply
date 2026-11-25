# VK2: visible_keys() reads moodle/user:viewalldetails in the context with has_capability() alone.
s#\\enrol_apply\\local\\groupscope::holds_in_course\('moodle/user:viewalldetails', \$context, \$userid\)#has_capability('moodle/user:viewalldetails', \$context, \$userid)#;

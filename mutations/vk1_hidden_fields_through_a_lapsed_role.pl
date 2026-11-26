# VK1: visible_keys() reads the hidden fields' capability with has_capability() alone.
s#\\enrol_apply\\local\\groupscope::holds_in_course\(\$hiddencapability, \$context, \$userid\)#has_capability(\$hiddencapability, \$context, \$userid)#;

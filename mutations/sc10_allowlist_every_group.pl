# SC10: confirm_enrolment() allowlists a posted group against every group of the course again.
s#\$allowed = \\enrol_apply\\local\\groupscope::choosable_groups\(\(int\) \$instance->courseid\);#\$allowed = groups_get_all_groups(\$instance->courseid);#;

# SC8: the queue's and review page's chooser lists every group of the course again.
s#\$coursegroups = \\enrol_apply\\local\\groupscope::choosable_groups\(\(int\) \$instance->courseid\);#\$coursegroups = groups_get_all_groups(\$instance->courseid);#;

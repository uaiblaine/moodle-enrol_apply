# SC9: the participants page's bulk form offers every group of the course again (V4: its options
# come from local\groupfacts, as the queue's chooser's do).
s#\$coursegroups = groupscope::choosable_groups\(\$courseid\);#\$coursegroups = groups_get_all_groups(\$courseid);#;

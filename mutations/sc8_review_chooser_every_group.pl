# SC8: the queue's and review page's chooser lists every group of the course again (the read
# moved to local\groupfacts for V4; the participants page's bulk form shares it, see SC9).
s#\$coursegroups = groupscope::choosable_groups\(\$courseid\);#\$coursegroups = groups_get_all_groups(\$courseid);#;

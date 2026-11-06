# SC9: the participants page's bulk form offers every group of the course again.
s#foreach \(\\enrol_apply\\local\\groupscope::choosable_groups\(\$courseid\) as \$group\) \{#foreach (groups_get_all_groups(\$courseid) as \$group) {#;

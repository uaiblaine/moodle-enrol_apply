# LP24: the fields handler's can_view() admits a lapsed report role.
s#\|\| \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:viewreports', \$context\)\);#|| has_capability('enrol/apply:viewreports', \$context));#;

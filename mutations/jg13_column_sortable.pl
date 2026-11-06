# JG13: the groups-joined column becomes sortable, placing withheld cells among the names.
s#(->add_field\(submissionhelper::joined_groups_sql\(\$alias\), 'joinedgroups'\)\n\s*)->set_is_sortable\(false\)#${1}->set_is_sortable(true)#;

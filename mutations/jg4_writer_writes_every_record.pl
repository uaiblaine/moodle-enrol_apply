# JG4: the groups joined are written on every record of the user enrolment, not the newest.
s#\$DB->set_field\('enrol_apply_submission', 'joinedgroups', implode\(',', \$clean\), \['id' => \$row->id\]\);#\$DB->set_field('enrol_apply_submission', 'joinedgroups', implode(',', \$clean), ['userenrolmentid' => \$userenrolmentid]);#;

# GJ2: the key's-group line is shown under the choice answer instead of the key.
s#\$mform->hideIf\('groupselectkey', 'groupdecided', 'neq', \$question::KEY\);#\$mform->hideIf('groupselectkey', 'groupdecided', 'neq', \$question::CHOICE);#;

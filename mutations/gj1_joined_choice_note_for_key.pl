# GJ1: the chosen-group line is shown under the key answer instead of the choice.
s#\$mform->hideIf\('groupselectchoice', 'groupdecided', 'neq', \$question::CHOICE\);#\$mform->hideIf('groupselectchoice', 'groupdecided', 'neq', \$question::KEY);#;

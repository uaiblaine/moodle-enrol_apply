# LP22: the warning after an approval names an overfilled group through a lapsed course role.
s#groupscope::holds_in_course\('enrol/apply:manageapplications', \$context, \$readerid\)#has_capability('enrol/apply:manageapplications', \$context, \$readerid)#;

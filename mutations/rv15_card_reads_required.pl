# RV15: the enrolment page's card reads required(), so the reviewed mode gets the key-or-review card.
s#\$keyonly = \\enrol_apply\\local\\groupkey::mandatory\(\$instance\);#\$keyonly = \\enrol_apply\\local\\groupkey::required(\$instance);#;

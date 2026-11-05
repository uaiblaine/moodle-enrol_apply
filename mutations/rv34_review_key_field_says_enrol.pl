# RV34: the form's missing-key sentence says to enrol on the reviewed mode.
s#return \\enrol_apply\\local\\groupkey::admits\(\$this->get_instance\(\)\)\n(\s*)\? get_string\('groupkeyrequired', 'enrol_apply'\)#return true\n${1}? get_string('groupkeyrequired', 'enrol_apply')#;

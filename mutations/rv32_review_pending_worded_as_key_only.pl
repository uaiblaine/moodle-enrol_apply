# RV32: the reviewed mode's waiting-applications warning says switching does not decide them.
s#get_string\(\$review \? 'keyreviewpending' : 'keyonlypending', 'enrol_apply', \$waiting\)#get_string('keyonlypending', 'enrol_apply', \$waiting)#;

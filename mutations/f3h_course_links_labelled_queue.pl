# F3H: label every notice's link as the approval queue, the applicant's course links included.
s#\$type === 'application' \? get_string\('applymanage', 'enrol_apply'\) : get_string\('course'\)#get_string('applymanage', 'enrol_apply')#s;

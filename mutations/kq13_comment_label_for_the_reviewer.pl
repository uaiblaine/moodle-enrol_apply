# KQ13: a key-only instance's comment box addresses a reviewer nobody is.
s#get_string\(groupkey::required\(\$instance\) \? 'applycomment_required' : 'applycomment', 'enrol_apply'\)#get_string('applycomment', 'enrol_apply')#;
